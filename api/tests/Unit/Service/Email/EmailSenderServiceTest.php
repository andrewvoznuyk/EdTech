<?php

namespace App\Tests\Unit\Service\Email;

use App\Entity\User;
use App\Service\Email\EmailSenderService;
use App\Service\Email\Strategy\VerificationEmail;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use LogicException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Contracts\Translation\TranslatorInterface;

class EmailSenderServiceTest extends TestCase
{
    /**
     * @var MailerInterface&MockObject
     */
    private MailerInterface&MockObject $mailer;

    /**
     * @var JWTEncoderInterface&MockObject
     */
    private JWTEncoderInterface&MockObject $jwtEncoder;

    /**
     * @var TranslatorInterface&MockObject
     */
    private TranslatorInterface&MockObject $translator;

    /**
     * @var RateLimiterFactory
     */
    private RateLimiterFactory $emailLimiter;

    /**
     * @var EmailSenderService
     */
    private EmailSenderService $emailSenderService;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->jwtEncoder = $this->createMock(JWTEncoderInterface::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->translator->method('trans')->willReturnArgument(0);

        $this->emailLimiter = new RateLimiterFactory(
            ['id' => 'email', 'policy' => 'fixed_window', 'limit' => 1, 'interval' => '1 minute'],
            new InMemoryStorage()
        );

        $this->emailSenderService = new EmailSenderService(
            $this->mailer,
            $this->jwtEncoder,
            $this->emailLimiter,
            $this->translator
        );
    }

    /**
     * @return void
     */
    public function testSendEmailThrowsIfNoStrategy(): void
    {
        $user = new User();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Email strategy must be set before sending an email.');

        $this->emailSenderService->sendEmail($user);
    }

    /**
     * @return void
     */
    public function testSendEmailThrowsIfRateLimitExceeded(): void
    {
        $user = new User();
        $user->setEmail('rate_limited@example.com');

        $this->emailSenderService->setStrategy(new VerificationEmail());

        // First consume succeeds
        $this->jwtEncoder->method('encode')->willReturn('jwt.token');
        $this->emailSenderService->sendEmail($user);

        // Second consume within 1 minute exceeds limit of 1
        $this->expectException(TooManyRequestsHttpException::class);
        $this->emailSenderService->sendEmail($user);
    }

    /**
     * @return void
     */
    public function testSendEmailSuccess(): void
    {
        $user = new User();
        $user->setEmail('success@example.com');

        $this->emailSenderService->setStrategy(new VerificationEmail());

        $this->jwtEncoder->expects($this->once())
            ->method('encode')
            ->willReturn('jwt.verification.token');

        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (TemplatedEmail $email) {
                return $email->getTo()[0]->getAddress() === 'success@example.com'
                    && $email->getSubject() === 'subject.verification';
            }));

        $this->emailSenderService->sendEmail($user);
    }

    /**
     * @return void
     */
    public function testResetLimit(): void
    {
        $user = new User();
        $user->setEmail('reset_user@example.com');

        $this->emailSenderService->setStrategy(new VerificationEmail());
        $this->jwtEncoder->method('encode')->willReturn('token');

        // First send consumes token
        $this->emailSenderService->sendEmail($user);

        // Reset limit
        $this->emailSenderService->resetLimit('reset_user@example.com', 'verification');

        // Next send succeeds again!
        $this->emailSenderService->sendEmail($user);
        $this->assertTrue(true);
    }
}
