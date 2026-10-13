<?php

namespace App\Service\TwoFactor\Provider;

use App\Entity\User;
use App\Enum\EmailType;
use App\Enum\TwoFactorMethodEnum;
use App\Service\Email\EmailSenderService;
use App\Service\Email\Strategy\Resend2FAEmail;
use DateMalformedStringException;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTEncodeFailureException;
use Random\RandomException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

readonly class EmailTwoFactorProvider implements TwoFactorProviderInterface
{
    private const int MAX_ATTEMPTS = 3;
    private const string CODE_TTL_MODIFIER = '+5 minutes';

    /**
     * @param EntityManagerInterface $entityManager
     * @param EmailSenderService $emailService
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EmailSenderService $emailService
    ) {
    }

    /**
     * @return TwoFactorMethodEnum
     */
    public function getName(): TwoFactorMethodEnum
    {
        return TwoFactorMethodEnum::EMAIL;
    }

    /**
     * @param User $user
     * @return void
     * @throws DateMalformedStringException
     * @throws JWTEncodeFailureException
     * @throws TransportExceptionInterface
     * @throws RandomException
     * @throws TooManyRequestsHttpException
     */
    public function prepareAuthentication(User $user): void
    {
        $now = new DateTimeImmutable();
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        try {
            $this->emailService->setStrategy(new Resend2FAEmail($code));
            $this->emailService->sendEmail($user);

            $user->setAuthCode(hash('sha256', $code));
            $user->setAuthCodeExpiresAt($now->modify(self::CODE_TTL_MODIFIER));
            $user->setAuthCodeAttempts(0);
            $this->entityManager->flush();
        } catch (TooManyRequestsHttpException $exception) {
            $expiresAt = $user->getAuthCodeExpiresAt();
            if (
                null !== $user->getAuthCode()
                && null !== $expiresAt
                && $expiresAt > $now
                && $user->getAuthCodeAttempts() < self::MAX_ATTEMPTS
            ) {
                return;
            }

            throw $exception;
        }
    }

    /**
     * @param User $user
     * @param string $code
     * @return bool
     */
    public function validateAuthentication(User $user, string $code): bool
    {
        $cleanCode = str_replace([' ', '-'], '', trim($code));
        if (null === $user->getAuthCode() || '' === $cleanCode) {
            return false;
        }

        if ($user->getAuthCodeAttempts() >= self::MAX_ATTEMPTS) {
            $user->setAuthCode(null);
            $user->setAuthCodeExpiresAt(null);
            $user->setAuthCodeAttempts(0);
            $this->entityManager->flush();

            return false;
        }

        $user->incrementAuthCodeAttempts();

        $expiresAt = $user->getAuthCodeExpiresAt();
        if (null === $expiresAt || $expiresAt < new DateTimeImmutable()) {
            $this->entityManager->flush();

            return false;
        }

        $expectedHash = (string) $user->getAuthCode();
        $providedHash = hash('sha256', $cleanCode);

        if (!hash_equals($expectedHash, $providedHash)) {
            if ($user->getAuthCodeAttempts() >= self::MAX_ATTEMPTS) {
                $user->setAuthCode(null);
                $user->setAuthCodeExpiresAt(null);
                $user->setAuthCodeAttempts(0);
            }
            $this->entityManager->flush();

            return false;
        }

        $user->setAuthCode(null);
        $user->setAuthCodeExpiresAt(null);
        $user->setAuthCodeAttempts(0);
        $this->entityManager->flush();

        $this->emailService->resetLimit((string) $user->getEmail(), EmailType::RESEND_2FA->value);

        return true;
    }
}
