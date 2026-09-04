<?php

namespace App\Service\TwoFactor\Provider;

use App\Entity\User;
use App\Enum\TwoFactorMethodEnum;
use App\Service\Email\EmailSenderService;
use App\Service\Email\Strategy\Resend2FAEmail;
use DateMalformedStringException;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTEncodeFailureException;
use Random\RandomException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

readonly class EmailTwoFactorProvider implements TwoFactorProviderInterface
{
    /**
     * @param EntityManagerInterface $entityManager
     * @param EmailSenderService $emailService
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EmailSenderService $emailService
    ) {}

    /**
     * @return TwoFactorMethodEnum
     */
    public function getName(): TwoFactorMethodEnum
    {
        return TwoFactorMethodEnum::EMAIL;
    }

    /**
     * @throws DateMalformedStringException
     * @throws JWTEncodeFailureException
     * @throws TransportExceptionInterface
     * @throws RandomException
     */
    public function prepareAuthentication(User $user): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $now = new DateTimeImmutable();

        $this->emailService->setStrategy(new Resend2FAEmail($code));
        $this->emailService->sendEmail($user);

        $user->setAuthCode($code);
        $user->setAuthCodeExpiresAt($now->modify('+15 minutes'));
        $this->entityManager->flush();
    }


    /**
     * @param User $user
     * @param string $code
     * @return bool
     */
    public function validateAuthentication(User $user, string $code): bool
    {
        if ($user->getAuthCode() !== $code) {
            return false;
        }

        if ($user->getAuthCodeExpiresAt() < new DateTimeImmutable()) {
            return false;
        }

        $user->setAuthCode(null);
        $user->setAuthCodeExpiresAt(null);
        $this->entityManager->flush();

        return true;
    }
}
