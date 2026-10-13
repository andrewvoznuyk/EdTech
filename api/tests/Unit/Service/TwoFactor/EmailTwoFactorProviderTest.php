<?php

namespace App\Tests\Unit\Service\TwoFactor;

use App\Entity\User;
use App\Enum\EmailType;
use App\Enum\TwoFactorMethodEnum;
use App\Service\Email\EmailSenderService;
use App\Service\Email\Strategy\Resend2FAEmail;
use App\Service\TwoFactor\Provider\EmailTwoFactorProvider;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class EmailTwoFactorProviderTest extends TestCase
{
    /**
     * @var EntityManagerInterface&MockObject
     */
    private EntityManagerInterface&MockObject $entityManager;

    /**
     * @var EmailSenderService&MockObject
     */
    private EmailSenderService&MockObject $emailService;

    /**
     * @var EmailTwoFactorProvider
     */
    private EmailTwoFactorProvider $provider;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->emailService = $this->createMock(EmailSenderService::class);
        $this->provider = new EmailTwoFactorProvider(
            $this->entityManager,
            $this->emailService
        );
    }

    /**
     * @return void
     */
    public function testGetName(): void
    {
        $this->assertSame(TwoFactorMethodEnum::EMAIL, $this->provider->getName());
    }

    /**
     * @return void
     */
    public function testPrepareAuthenticationSuccess(): void
    {
        $user = new User();
        $user->setEmail('user@example.com');

        $this->emailService->expects($this->once())
            ->method('setStrategy')
            ->with($this->isInstanceOf(Resend2FAEmail::class));

        $this->emailService->expects($this->once())
            ->method('sendEmail')
            ->with($user);

        $this->entityManager->expects($this->once())->method('flush');

        $this->provider->prepareAuthentication($user);

        $this->assertNotNull($user->getAuthCode());
        $this->assertNotNull($user->getAuthCodeExpiresAt());
        $this->assertSame(0, $user->getAuthCodeAttempts());
    }

    /**
     * @return void
     */
    public function testPrepareAuthenticationSwallowsRateLimitIfCodeStillValid(): void
    {
        $user = new User();
        $user->setEmail('user@example.com');
        $user->setAuthCode('existing_hash');
        $user->setAuthCodeExpiresAt(new DateTimeImmutable('+3 minutes'));
        $user->setAuthCodeAttempts(1);

        $this->emailService->expects($this->once())
            ->method('sendEmail')
            ->willThrowException(new TooManyRequestsHttpException());

        // Should NOT rethrow because code is still valid and attempts < 3
        $this->provider->prepareAuthentication($user);
        $this->assertSame('existing_hash', $user->getAuthCode());
    }

    /**
     * @return void
     */
    public function testPrepareAuthenticationRethrowsRateLimitIfCodeExpired(): void
    {
        $user = new User();
        $user->setEmail('user@example.com');
        $user->setAuthCode('existing_hash');
        $user->setAuthCodeExpiresAt(new DateTimeImmutable('-1 minute'));
        $user->setAuthCodeAttempts(1);

        $this->emailService->expects($this->once())
            ->method('sendEmail')
            ->willThrowException(new TooManyRequestsHttpException());

        $this->expectException(TooManyRequestsHttpException::class);

        $this->provider->prepareAuthentication($user);
    }

    /**
     * @return void
     */
    public function testValidateAuthenticationSuccess(): void
    {
        $user = new User();
        $user->setEmail('user@example.com');
        $plainCode = '123456';
        $user->setAuthCode(hash('sha256', $plainCode));
        $user->setAuthCodeExpiresAt(new DateTimeImmutable('+5 minutes'));
        $user->setAuthCodeAttempts(0);

        $this->emailService->expects($this->once())
            ->method('resetLimit')
            ->with('user@example.com', EmailType::RESEND_2FA->value);

        $this->entityManager->expects($this->once())->method('flush');

        $isValid = $this->provider->validateAuthentication($user, '123-456');

        $this->assertTrue($isValid);
        $this->assertNull($user->getAuthCode());
        $this->assertNull($user->getAuthCodeExpiresAt());
        $this->assertSame(0, $user->getAuthCodeAttempts());
    }

    /**
     * @return void
     */
    public function testValidateAuthenticationFailsOnWrongCode(): void
    {
        $user = new User();
        $user->setEmail('user@example.com');
        $user->setAuthCode(hash('sha256', '123456'));
        $user->setAuthCodeExpiresAt(new DateTimeImmutable('+5 minutes'));
        $user->setAuthCodeAttempts(0);

        $this->entityManager->expects($this->once())->method('flush');

        $isValid = $this->provider->validateAuthentication($user, '999999');

        $this->assertFalse($isValid);
        $this->assertSame(1, $user->getAuthCodeAttempts());
        $this->assertNotNull($user->getAuthCode());
    }

    /**
     * @return void
     */
    public function testValidateAuthenticationFailsAndClearsOnMaxAttempts(): void
    {
        $user = new User();
        $user->setEmail('user@example.com');
        $user->setAuthCode(hash('sha256', '123456'));
        $user->setAuthCodeExpiresAt(new DateTimeImmutable('+5 minutes'));
        $user->setAuthCodeAttempts(2);

        $this->entityManager->expects($this->once())->method('flush');

        // Third attempt with wrong code reaches MAX_ATTEMPTS (3)
        $isValid = $this->provider->validateAuthentication($user, '000000');

        $this->assertFalse($isValid);
        $this->assertNull($user->getAuthCode());
    }

    /**
     * @return void
     */
    public function testValidateAuthenticationFailsIfExpired(): void
    {
        $user = new User();
        $user->setEmail('user@example.com');
        $user->setAuthCode(hash('sha256', '123456'));
        $user->setAuthCodeExpiresAt(new DateTimeImmutable('-5 minutes'));
        $user->setAuthCodeAttempts(0);

        $this->entityManager->expects($this->once())->method('flush');

        $isValid = $this->provider->validateAuthentication($user, '123456');

        $this->assertFalse($isValid);
    }
}
