<?php

namespace App\Service\Email;

use App\Entity\User;
use App\Service\Email\Strategy\Email;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTEncodeFailureException;
use LogicException;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Contracts\Translation\TranslatorInterface;

class EmailSenderService
{
    /**
     * @var Email|null
     */
    private ?Email $strategy = null;

    /**
     * @param MailerInterface $mailer
     * @param JWTEncoderInterface $jwtEncoder
     * @param RateLimiterFactory $emailLimiter
     * @param TranslatorInterface $translator
     */
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly JWTEncoderInterface $jwtEncoder,
        #[Target]
        private readonly RateLimiterFactory $emailLimiter,
        private readonly TranslatorInterface $translator
    ) {}

    /**
     * @param Email $strategy
     * @return void
     */
    public function setStrategy(Email $strategy): void
    {
        $this->strategy = $strategy;
    }

    /**
     * @param User $user
     * @return void
     * @throws JWTEncodeFailureException
     * @throws TransportExceptionInterface
     */
    public function sendEmail(User $user): void
    {
        if (!$this->strategy) {
            throw new LogicException('Email strategy must be set before sending an email.');
        }

        $userEmail = $user->getEmail();
        $limiterKey = $this->strategy->performLimit($userEmail);
        $limiter = $this->emailLimiter->create($limiterKey);

        if (false === $limiter->consume(1)->isAccepted()) {
            throw new TooManyRequestsHttpException($this->translator->trans('error.rate_limit', [], 'email'));
        }

        $token = $this->jwtEncoder->encode([
            'email' => $user->getEmail(),
            'type'  => $this->strategy->performType(),
            'exp'   => time() + $this->strategy->performTtl()
        ]);

        $frontendUrl = $_ENV['FRONTEND_URL'] ?? 'https://localhost';
        $context = $this->strategy->performTemplateContext($frontendUrl, $token);

        $email = new TemplatedEmail()
            ->from('noreply@avante.com')
            ->to($userEmail)
            ->subject($this->translator->trans($this->strategy->performSubject(), [], 'email'))
            ->htmlTemplate($this->strategy->performTemplate())
            ->context($context);

        $this->mailer->send($email);
    }

    /**
     * @param string $userEmail
     * @param string $type
     * @return void
     */
    public function resetLimit(string $userEmail, string $type): void
    {
        $limiterKey = $userEmail . '_' . $type;
        $this->emailLimiter->create($limiterKey)->reset();
    }
}
