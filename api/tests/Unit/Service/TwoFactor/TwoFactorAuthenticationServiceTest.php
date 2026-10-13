<?php

namespace App\Tests\Unit\Service\TwoFactor;

use App\Entity\User;
use App\Entity\UserTwoFactorMethod;
use App\Enum\TwoFactorMethodEnum;
use App\Service\TwoFactor\Token\PreAuthTokenServiceInterface;
use App\Service\TwoFactor\TwoFactorAuthenticationService;
use App\Service\TwoFactor\TwoFactorVerifierInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Contracts\Translation\TranslatorInterface;

class TwoFactorAuthenticationServiceTest extends TestCase
{
    /**
     * @var PreAuthTokenServiceInterface&MockObject
     */
    private PreAuthTokenServiceInterface&MockObject $preAuthTokenService;

    /**
     * @var TwoFactorVerifierInterface&MockObject
     */
    private TwoFactorVerifierInterface&MockObject $verifier;

    /**
     * @var TranslatorInterface&MockObject
     */
    private TranslatorInterface&MockObject $translator;

    /**
     * @var RateLimiterFactory
     */
    private RateLimiterFactory $authLimiterFactory;

    /**
     * @var RateLimiterFactory
     */
    private RateLimiterFactory $resendLimiterFactory;

    /**
     * @var TwoFactorAuthenticationService
     */
    private TwoFactorAuthenticationService $service;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->preAuthTokenService = $this->createMock(PreAuthTokenServiceInterface::class);
        $this->verifier = $this->createMock(TwoFactorVerifierInterface::class);
        $this->translator = $this->createMock(TranslatorInterface::class);

        $this->translator->method('trans')->willReturnArgument(0);

        $this->authLimiterFactory = new RateLimiterFactory(
            ['id' => 'auth_2fa', 'policy' => 'fixed_window', 'limit' => 3, 'interval' => '1 minute'],
            new InMemoryStorage()
        );

        $this->resendLimiterFactory = new RateLimiterFactory(
            ['id' => 'resend_2fa', 'policy' => 'fixed_window', 'limit' => 3, 'interval' => '1 minute'],
            new InMemoryStorage()
        );

        $this->service = new TwoFactorAuthenticationService(
            $this->preAuthTokenService,
            $this->authLimiterFactory,
            $this->resendLimiterFactory,
            $this->verifier,
            $this->translator
        );
    }

    /**
     * @return void
     */
    public function testAuthenticateThrowsIfCodeMissing(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('user.two_factor.code_required');

        $this->service->authenticate('token', '', 'email');
    }

    /**
     * @return void
     */
    public function testAuthenticateThrowsIfTokenMissing(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('user.two_factor.token_required');

        $this->service->authenticate('', '123456', 'email');
    }

    /**
     * @return void
     */
    public function testAuthenticateThrowsIfRateLimitExceeded(): void
    {
        $user = new User();
        $user->setEmail('rate_limited@example.com');

        $method = new UserTwoFactorMethod();
        $method->setMethod(TwoFactorMethodEnum::EMAIL);
        $method->setIsActive(true);
        $user->addTwoFactorMethod($method);

        $this->preAuthTokenService->method('validateToken')->willReturn($user);
        $this->verifier->method('validate')->willReturn(false);

        // Exhaust the limiter (limit is 3)
        for ($i = 0; $i < 3; $i++) {
            try {
                $this->service->authenticate('token', 'wrong', 'email');
            } catch (BadRequestHttpException) {
            }
        }

        $this->expectException(TooManyRequestsHttpException::class);
        $this->service->authenticate('token', '123456', 'email');
    }

    /**
     * @return void
     */
    public function testAuthenticateSuccess(): void
    {
        $user = new User();
        $user->setEmail('auth_success@example.com');

        $method = new UserTwoFactorMethod();
        $method->setMethod(TwoFactorMethodEnum::EMAIL);
        $method->setIsActive(true);
        $user->addTwoFactorMethod($method);

        $this->preAuthTokenService->method('validateToken')->willReturn($user);

        $this->verifier->expects($this->once())
            ->method('validate')
            ->with($user, '123456', TwoFactorMethodEnum::EMAIL)
            ->willReturn(true);

        $this->preAuthTokenService->expects($this->once())
            ->method('consumeToken')
            ->with('token');

        $resultUser = $this->service->authenticate('token', '123456', 'email');
        $this->assertSame($user, $resultUser);
    }

    /**
     * @return void
     */
    public function testAuthenticateThrowsIfCodeInvalid(): void
    {
        $user = new User();
        $user->setEmail('invalid_code@example.com');

        $method = new UserTwoFactorMethod();
        $method->setMethod(TwoFactorMethodEnum::EMAIL);
        $method->setIsActive(true);
        $user->addTwoFactorMethod($method);

        $this->preAuthTokenService->method('validateToken')->willReturn($user);
        $this->verifier->method('validate')->willReturn(false);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('user.two_factor.invalid_code');

        $this->service->authenticate('token', '999999', 'email');
    }

    /**
     * @return void
     */
    public function testResendSuccess(): void
    {
        $user = new User();
        $user->setEmail('resend_success@example.com');

        $method = new UserTwoFactorMethod();
        $method->setMethod(TwoFactorMethodEnum::EMAIL);
        $method->setIsActive(true);
        $user->addTwoFactorMethod($method);

        $this->preAuthTokenService->method('validateToken')->willReturn($user);

        $this->verifier->expects($this->once())
            ->method('prepare')
            ->with($user, TwoFactorMethodEnum::EMAIL);

        $this->service->resend('token', 'email');
        $this->assertTrue(true);
    }

    /**
     * @return void
     */
    public function testResendThrowsForNonEmailMethod(): void
    {
        $user = new User();
        $this->preAuthTokenService->method('validateToken')->willReturn($user);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('user.two_factor.invalid_method');

        $this->service->resend('token', 'totp');
    }
}
