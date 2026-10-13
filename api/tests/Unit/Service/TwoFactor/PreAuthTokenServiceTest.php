<?php

namespace App\Tests\Unit\Service\TwoFactor;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\TwoFactor\Token\PreAuthTokenService;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

class PreAuthTokenServiceTest extends TestCase
{
    /**
     * @var JWTEncoderInterface&MockObject
     */
    private JWTEncoderInterface&MockObject $jwtEncoder;

    /**
     * @var EntityManagerInterface&MockObject
     */
    private EntityManagerInterface&MockObject $entityManager;

    /**
     * @var CacheItemPoolInterface&MockObject
     */
    private CacheItemPoolInterface&MockObject $cachePool;

    /**
     * @var TranslatorInterface&MockObject
     */
    private TranslatorInterface&MockObject $translator;

    /**
     * @var UserRepository&MockObject
     */
    private UserRepository&MockObject $userRepository;

    /**
     * @var PreAuthTokenService
     */
    private PreAuthTokenService $service;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->jwtEncoder = $this->createMock(JWTEncoderInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->cachePool = $this->createMock(CacheItemPoolInterface::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->userRepository = $this->createMock(UserRepository::class);

        $this->entityManager->method('getRepository')
            ->with(User::class)
            ->willReturn($this->userRepository);

        $this->translator->method('trans')->willReturnArgument(0);

        $this->service = new PreAuthTokenService(
            $this->jwtEncoder,
            $this->entityManager,
            $this->cachePool,
            $this->translator
        );
    }

    /**
     * @return void
     */
    public function testCreateToken(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');

        $this->jwtEncoder->expects($this->once())
            ->method('encode')
            ->with($this->callback(function (array $payload) {
                return ($payload['purpose'] ?? '') === '2fa_challenge'
                    && ($payload['email'] ?? '') === 'test@example.com'
                    && isset($payload['jti'], $payload['exp']);
            }))
            ->willReturn('jwt.preauth.token');

        $token = $this->service->createToken($user);
        $this->assertSame('jwt.preauth.token', $token);
    }

    /**
     * @return void
     */
    public function testValidateTokenSuccess(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setIsActive(true);

        $token = 'valid.token.string';
        $tokenHash = hash('sha256', $token);

        $this->jwtEncoder->expects($this->once())
            ->method('decode')
            ->with($token)
            ->willReturn([
                'purpose' => '2fa_challenge',
                'email' => 'test@example.com',
            ]);

        $this->cachePool->expects($this->once())
            ->method('hasItem')
            ->with('2fa_revoked_' . $tokenHash)
            ->willReturn(false);

        $this->userRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => 'test@example.com'])
            ->willReturn($user);

        $resultUser = $this->service->validateToken($token);
        $this->assertSame($user, $resultUser);
    }

    /**
     * @return void
     */
    public function testValidateTokenThrowsOnInvalidJwt(): void
    {
        $this->jwtEncoder->expects($this->once())
            ->method('decode')
            ->willThrowException(new JWTDecodeFailureException(JWTDecodeFailureException::INVALID_TOKEN, 'Invalid token'));

        $this->expectException(UnauthorizedHttpException::class);
        $this->service->validateToken('invalid.token');
    }

    /**
     * @return void
     */
    public function testValidateTokenThrowsOnWrongPurpose(): void
    {
        $this->jwtEncoder->method('decode')->willReturn(['purpose' => 'auth']);

        $this->expectException(UnauthorizedHttpException::class);
        $this->service->validateToken('token');
    }

    /**
     * @return void
     */
    public function testValidateTokenThrowsIfRevoked(): void
    {
        $token = 'revoked.token';
        $tokenHash = hash('sha256', $token);

        $this->jwtEncoder->method('decode')->willReturn(['purpose' => '2fa_challenge', 'email' => 'a@b.com']);
        $this->cachePool->method('hasItem')
            ->with('2fa_revoked_' . $tokenHash)
            ->willReturn(true);

        $this->expectException(UnauthorizedHttpException::class);
        $this->service->validateToken($token);
    }

    /**
     * @return void
     */
    public function testValidateTokenThrowsIfUserInactive(): void
    {
        $user = new User();
        $user->setEmail('inactive@example.com');
        $user->setIsActive(false);

        $token = 'token';
        $this->jwtEncoder->method('decode')->willReturn(['purpose' => '2fa_challenge', 'email' => 'inactive@example.com']);
        $this->cachePool->method('hasItem')->willReturn(false);
        $this->userRepository->method('findOneBy')->willReturn($user);

        $this->expectException(UnauthorizedHttpException::class);
        $this->service->validateToken($token);
    }

    /**
     * @return void
     */
    public function testConsumeToken(): void
    {
        $token = 'token.to.consume';
        $tokenHash = hash('sha256', $token);

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects($this->once())->method('set')->with(true);
        $cacheItem->expects($this->once())->method('expiresAfter')->with(300);

        $this->cachePool->expects($this->once())
            ->method('getItem')
            ->with('2fa_revoked_' . $tokenHash)
            ->willReturn($cacheItem);

        $this->cachePool->expects($this->once())
            ->method('save')
            ->with($cacheItem);

        $this->service->consumeToken($token);
    }
}
