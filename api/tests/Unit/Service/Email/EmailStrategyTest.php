<?php

namespace App\Tests\Unit\Service\Email;

use App\Enum\EmailType;
use App\Service\Email\Strategy\PasswordResetEmail;
use App\Service\Email\Strategy\Resend2FAEmail;
use App\Service\Email\Strategy\RestoreAccountEmail;
use App\Service\Email\Strategy\VerificationEmail;
use PHPUnit\Framework\TestCase;

class EmailStrategyTest extends TestCase
{
    /**
     * @return void
     */
    public function testVerificationEmailStrategy(): void
    {
        $strategy = new VerificationEmail();

        $this->assertSame(EmailType::VERIFICATION->value, $strategy->performType());
        $this->assertSame('subject.verification', $strategy->performSubject());
        $this->assertSame('emails/verification.html.twig', $strategy->performTemplate());
        $this->assertSame(900, $strategy->performTtl());
        $this->assertSame('user@example.com_verification', $strategy->performLimit('user@example.com'));

        $context = $strategy->performTemplateContext('https://app.com', 'token123');
        $this->assertSame('https://app.com/verify-email?token=token123', $context['verify_url']);
    }

    /**
     * @return void
     */
    public function testPasswordResetEmailStrategy(): void
    {
        $strategy = new PasswordResetEmail();

        $this->assertSame(EmailType::PASSWORD_RESET->value, $strategy->performType());
        $this->assertSame('subject.password_reset', $strategy->performSubject());
        $this->assertSame('emails/password_reset.html.twig', $strategy->performTemplate());
        $this->assertSame(3600, $strategy->performTtl());
        $this->assertSame('user@example.com_password_reset', $strategy->performLimit('user@example.com'));

        $context = $strategy->performTemplateContext('https://app.com', 'token123');
        $this->assertSame('https://app.com/reset-password?token=token123', $context['reset_url']);
    }

    /**
     * @return void
     */
    public function testResend2FAEmailStrategy(): void
    {
        $strategy = new Resend2FAEmail('654321');

        $this->assertSame(EmailType::RESEND_2FA->value, $strategy->performType());
        $this->assertSame('subject.resend_2fa', $strategy->performSubject());
        $this->assertSame('emails/resend_2fa.html.twig', $strategy->performTemplate());
        $this->assertSame(900, $strategy->performTtl());
        $this->assertSame('user@example.com_resend_2fa', $strategy->performLimit('user@example.com'));

        $context = $strategy->performTemplateContext('https://app.com', 'token123');
        $this->assertSame('654321', $context['auth_code']);
    }

    /**
     * @return void
     */
    public function testRestoreAccountEmailStrategy(): void
    {
        $strategy = new RestoreAccountEmail();

        $this->assertSame(EmailType::RESTORE_ACCOUNT->value, $strategy->performType());
        $this->assertSame('subject.restore_account', $strategy->performSubject());
        $this->assertSame('emails/restore_account.html.twig', $strategy->performTemplate());
        $this->assertSame(3600, $strategy->performTtl());
        $this->assertSame('user@example.com_restore_account', $strategy->performLimit('user@example.com'));

        $context = $strategy->performTemplateContext('https://app.com', 'token123');
        $this->assertSame('https://app.com/restore-account?token=token123', $context['action_url']);
    }
}
