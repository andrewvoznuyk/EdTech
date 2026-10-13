<?php

namespace App\Tests\Unit\Service\User;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\User\TokenUserResolver;
use DateTime;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class TokenUserResolverTest extends TestCase
{
    /**
     * @var JWTEncoderInterface&MockObject
     */
    private JWTEncoderInterface&MockObject $jwtEncoder;

    /**
     * @var UserRepository&MockObject
     */
    private UserRepository&MockObject $userRepository;

    /**
     * @var JWTTokenManagerInterface&MockObject
     */
    private JWTTokenManagerInterface&MockObject $jwtManager;

    /**
     * @var RefreshTokenManagerInterface&MockObject
     */
    private RefreshTokenManagerInterface&MockObject $refreshTokenManager;

    /**
     * @var RefreshTokenGeneratorInterface&MockObject
     */
    private RefreshTokenGeneratorInterface&MockObject $refreshTokenGenerator;

    /**
     * @var TokenUserResolver
     */
    private TokenUserResolver $resolver;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->jwtEncoder = $this->createMock(JWTEncoderInterface::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->jwtManager = $this->createMock(JWTTokenManagerInterface::class);
        $this->refreshTokenManager = $this->createMock(RefreshTokenManagerInterface::class);
        $this->refreshTokenGenerator = $this->createMock(RefreshTokenGeneratorInterface::class);

        $this->resolver = new TokenUserResolver(
            $this->jwtEncoder,
            $this->userRepository,
            $this->jwtManager,
            $this->refreshTokenManager,
            $this->refreshTokenGenerator
        );
    }

    /**
     * @return void
     */
    public function testResolveThrowsIfTokenMissing(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Token is required for unauthorized users.');

        $this->resolver->resolve(null);
    }

    /**
     * @return void
     */
    public function testResolveThrowsIfDecodeFails(): void
    {
        $this->jwtEncoder->expects($this->once())
            ->method('decode')
            ->willThrowException(new JWTDecodeFailureException(JWTDecodeFailureException::INVALID_TOKEN, 'expired'));

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Invalid or expired token.');

        $this->resolver->resolve('invalid.token');
    }

    /**
     * @return void
     */
    public function testResolveThrowsIfTypeMismatch(): void
    {
        $this->jwtEncoder->method('decode')->willReturn(['email' => 'a@b.com', 'type' => 'password_reset']);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Invalid token type.');

        $this->resolver->resolve('token', 'restore_account');
    }

    /**
     * @return void
     */
    public function testResolveThrowsIfUserNotFound(): void
    {
        $this->jwtEncoder->method('decode')->willReturn(['email' => 'missing@example.com', 'type' => 'restore_account']);
        $this->userRepository->method('findOneBy')->with(['email' => 'missing@example.com'])->willReturn(null);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('User not found.');

        $this->resolver->resolve('token', 'restore_account');
    }

    /**
     * @return void
     */
    public function testResolveSuccess(): void
    {
        $user = new User();
        $user->setEmail('user@example.com');

        $this->jwtEncoder->method('decode')->willReturn(['email' => 'user@example.com', 'type' => 'restore_account']);
        $this->userRepository->method('findOneBy')->with(['email' => 'user@example.com'])->willReturn($user);

        $resultUser = $this->resolver->resolve('token', 'restore_account');
        $this->assertSame($user, $resultUser);
    }

    /**
     * @return void
     */
    public function testCreateAuthResponse(): void
    {
        $user = new User();
        $user->setEmail('user@example.com');

        $refreshToken = new RefreshToken();
        $refreshToken->setRefreshToken('refresh_token_value');
        $refreshToken->setValid(new DateTime('+1 month'));

        $this->jwtManager->expects($this->once())
            ->method('create')
            ->with($user)
            ->willReturn('jwt.access.token');

        $this->refreshTokenGenerator->expects($this->once())
            ->method('createForUserWithTtl')
            ->with($user, 2592000)
            ->willReturn($refreshToken);

        $this->refreshTokenManager->expects($this->once())
            ->method('save')
            ->with($refreshToken);

        $response = $this->resolver->createAuthResponse($user, 2592000);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertStringContainsString('jwt.access.token', (string) $response->getContent());
        $this->assertNotEmpty($response->headers->getCookies());
    }
}
