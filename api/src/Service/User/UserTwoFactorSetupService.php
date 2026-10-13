<?php

namespace App\Service\User;

use App\Entity\User;
use App\Entity\UserTwoFactorMethod;
use App\Enum\TwoFactorMethodEnum;
use App\Service\Security\Encryption\SecretCipherInterface;
use App\Service\TwoFactor\Provider\TotpTwoFactorProviderInterface;
use App\Service\TwoFactor\TwoFactorVerifierInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class UserTwoFactorSetupService implements UserTwoFactorSetupServiceInterface
{
    /**
     * @param EntityManagerInterface $entityManager
     * @param TwoFactorVerifierInterface $twoFactorVerifier
     * @param TotpTwoFactorProviderInterface $totpProvider
     * @param SecretCipherInterface $secretCipher
     * @param UserPasswordHasherInterface $passwordHasher
     * @param RateLimiterFactory $twoFactorAuthLimiter
     * @param RateLimiterFactory $twoFactorResendLimiter
     * @param TranslatorInterface $translator
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TwoFactorVerifierInterface $twoFactorVerifier,
        private TotpTwoFactorProviderInterface $totpProvider,
        private SecretCipherInterface $secretCipher,
        private UserPasswordHasherInterface $passwordHasher,
        private RateLimiterFactory $twoFactorAuthLimiter,
        private RateLimiterFactory $twoFactorResendLimiter,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param User $user
     * @param bool $makeDefault
     * @return void
     * @throws BadRequestHttpException
     * @throws TooManyRequestsHttpException
     */
    public function enableEmailTwoFactor(User $user, bool $makeDefault = false): void
    {
        $limiter = $this->twoFactorResendLimiter->create((string) $user->getEmail());
        if (false === $limiter->consume(1)->isAccepted()) {
            throw new TooManyRequestsHttpException();
        }

        $emailMethod = null;
        foreach ($user->getTwoFactorMethods() as $method) {
            if ($method->getMethod() === TwoFactorMethodEnum::EMAIL) {
                $emailMethod = $method;
                break;
            }
        }

        if (null !== $emailMethod && $emailMethod->isActive()) {
            throw new BadRequestHttpException($this->translator->trans('user.two_factor.method_already_enabled', [], 'validators'));
        }

        if (null === $emailMethod) {
            $emailMethod = new UserTwoFactorMethod();
            $emailMethod->setUser($user);
            $emailMethod->setMethod(TwoFactorMethodEnum::EMAIL);
            $emailMethod->setIsActive(false);
            $emailMethod->setIsDefault($makeDefault || !$user->has2faEnabled());

            $user->addTwoFactorMethod($emailMethod);
            $this->entityManager->persist($emailMethod);
        } elseif ($makeDefault && !$emailMethod->isDefault()) {
            foreach ($user->getTwoFactorMethods() as $method) {
                $method->setIsDefault($method === $emailMethod);
            }
        }

        $this->twoFactorVerifier->prepare($user, TwoFactorMethodEnum::EMAIL);
        $this->entityManager->flush();
    }

    /**
     * @param User $user
     * @param string $code
     * @return array<string, mixed>
     * @throws BadRequestHttpException
     * @throws TooManyRequestsHttpException
     */
    public function confirmEmailTwoFactor(User $user, string $code): array
    {
        $limiter = $this->twoFactorAuthLimiter->create((string) $user->getEmail());
        if (false === $limiter->consume(1)->isAccepted()) {
            throw new TooManyRequestsHttpException();
        }

        $cleanCode = str_replace([' ', '-'], '', trim($code));
        $isValid = $this->twoFactorVerifier->validate($user, $cleanCode, TwoFactorMethodEnum::EMAIL);
        if (!$isValid) {
            throw new BadRequestHttpException($this->translator->trans('user.two_factor.invalid_code', [], 'validators'));
        }

        $emailMethod = null;
        foreach ($user->getTwoFactorMethods() as $method) {
            if ($method->getMethod() === TwoFactorMethodEnum::EMAIL) {
                $emailMethod = $method;
                break;
            }
        }

        if (null === $emailMethod) {
            $emailMethod = new UserTwoFactorMethod();
            $emailMethod->setUser($user);
            $emailMethod->setMethod(TwoFactorMethodEnum::EMAIL);
            $user->addTwoFactorMethod($emailMethod);
            $this->entityManager->persist($emailMethod);
        }

        $emailMethod->setIsActive(true);

        $hasActiveDefault = false;
        foreach ($user->getTwoFactorMethods() as $method) {
            if ($method->isActive() && $method->isDefault() && $method !== $emailMethod) {
                $hasActiveDefault = true;
                break;
            }
        }
        if (!$hasActiveDefault) {
            $emailMethod->setIsDefault(true);
        } else {
            $emailMethod->setIsDefault(false);
        }

        $backupCodes = [];
        if (null === $user->getBackupCodes() || empty($user->getBackupCodes())) {
            $backupCodes = $this->twoFactorVerifier->generateBackupCodes($user);
        }

        $this->twoFactorAuthLimiter->create((string) $user->getEmail())->reset();
        $this->entityManager->flush();

        return [
            'backup_codes' => $backupCodes,
        ];
    }

    /**
     * @param User $user
     * @return array{secret: string, qr_code: string}
     * @throws TooManyRequestsHttpException
     */
    public function setupTotpTwoFactor(User $user): array
    {
        $limiter = $this->twoFactorResendLimiter->create((string) $user->getEmail());
        if (false === $limiter->consume(1)->isAccepted()) {
            throw new TooManyRequestsHttpException();
        }

        $plainSecret = $this->totpProvider->generateSecret();
        $qrCodeDataUri = $this->totpProvider->generateQrCodeDataUri($plainSecret, (string) $user->getEmail());
        $encryptedSecret = $this->secretCipher->encrypt($plainSecret);

        $totpMethod = null;
        foreach ($user->getTwoFactorMethods() as $method) {
            if ($method->getMethod() === TwoFactorMethodEnum::TOTP) {
                $totpMethod = $method;
                break;
            }
        }

        if (null !== $totpMethod && $totpMethod->isActive()) {
            throw new BadRequestHttpException($this->translator->trans('user.two_factor.method_already_enabled', [], 'validators'));
        }

        if (null === $totpMethod) {
            $totpMethod = new UserTwoFactorMethod();
            $totpMethod->setUser($user);
            $totpMethod->setMethod(TwoFactorMethodEnum::TOTP);
            $totpMethod->setIsDefault(!$user->has2faEnabled());
            $totpMethod->setIsActive(false);
            $user->addTwoFactorMethod($totpMethod);
            $this->entityManager->persist($totpMethod);
        }

        $totpMethod->setPendingSecret($encryptedSecret);
        $this->entityManager->flush();

        return [
            'secret' => $plainSecret,
            'qr_code' => $qrCodeDataUri,
        ];
    }

    /**
     * @param User $user
     * @param string $code
     * @return array<string, mixed>
     * @throws BadRequestHttpException
     * @throws TooManyRequestsHttpException
     */
    public function confirmTotpTwoFactor(User $user, string $code): array
    {
        $limiter = $this->twoFactorAuthLimiter->create((string) $user->getEmail());
        if (false === $limiter->consume(1)->isAccepted()) {
            throw new TooManyRequestsHttpException();
        }

        $totpMethod = null;
        foreach ($user->getTwoFactorMethods() as $method) {
            if ($method->getMethod() === TwoFactorMethodEnum::TOTP) {
                $totpMethod = $method;
                break;
            }
        }

        if (null === $totpMethod || null === $totpMethod->getPendingSecret()) {
            throw new BadRequestHttpException($this->translator->trans('user.two_factor.totp_not_initialized', [], 'validators'));
        }

        $cleanCode = str_replace([' ', '-'], '', trim($code));
        $plainSecret = $this->secretCipher->decrypt($totpMethod->getPendingSecret());
        $isValid = $this->totpProvider->verifyCode($plainSecret, $cleanCode);
        if (!$isValid) {
            throw new BadRequestHttpException($this->translator->trans('user.two_factor.invalid_code', [], 'validators'));
        }

        $totpMethod->setSecret($totpMethod->getPendingSecret());
        $totpMethod->setPendingSecret(null);
        $totpMethod->setIsActive(true);
        $totpMethod->setLastUsedSlice(intdiv(time(), 30));

        $hasActiveDefault = false;
        foreach ($user->getTwoFactorMethods() as $method) {
            if ($method->isActive() && $method->isDefault() && $method !== $totpMethod) {
                $hasActiveDefault = true;
                break;
            }
        }
        if (!$hasActiveDefault) {
            $totpMethod->setIsDefault(true);
        } else {
            $totpMethod->setIsDefault(false);
        }

        $backupCodes = [];
        if (null === $user->getBackupCodes() || empty($user->getBackupCodes())) {
            $backupCodes = $this->twoFactorVerifier->generateBackupCodes($user);
        }

        $this->twoFactorAuthLimiter->create((string) $user->getEmail())->reset();
        $this->entityManager->flush();

        return [
            'backup_codes' => $backupCodes,
        ];
    }

    /**
     * @param User $user
     * @param string $password
     * @param TwoFactorMethodEnum|null $method
     * @return void
     * @throws BadRequestHttpException
     * @throws TooManyRequestsHttpException
     */
    public function disableTwoFactor(User $user, string $password, ?TwoFactorMethodEnum $method = null): void
    {
        $this->verifyUserPassword($user, $password);

        if (null === $method) {
            foreach ($user->getTwoFactorMethods()->toArray() as $existingMethod) {
                $existingMethod->setIsActive(false);
                $existingMethod->setIsDefault(false);
                $existingMethod->setSecret(null);
                $existingMethod->setPendingSecret(null);
                $user->removeTwoFactorMethod($existingMethod);
                $this->entityManager->remove($existingMethod);
            }
            $user->setBackupCodes(null);
            $user->setAuthCode(null);
            $user->setAuthCodeExpiresAt(null);
            $user->setAuthCodeAttempts(0);
        } else {
            foreach ($user->getTwoFactorMethods()->toArray() as $existingMethod) {
                if ($existingMethod->getMethod() === $method) {
                    $existingMethod->setIsActive(false);
                    $existingMethod->setIsDefault(false);
                    $existingMethod->setSecret(null);
                    $existingMethod->setPendingSecret(null);
                    $user->removeTwoFactorMethod($existingMethod);
                    $this->entityManager->remove($existingMethod);
                }
            }

            if ($method === TwoFactorMethodEnum::EMAIL) {
                $user->setAuthCode(null);
                $user->setAuthCodeExpiresAt(null);
                $user->setAuthCodeAttempts(0);
            }

            $hasOtherActive = false;
            foreach ($user->getTwoFactorMethods() as $existingMethod) {
                if ($existingMethod->getMethod() !== $method && $existingMethod->isActive()) {
                    $hasOtherActive = true;
                    break;
                }
            }

            if (!$hasOtherActive) {
                $user->setBackupCodes(null);
            } else {
                $hasActiveDefault = false;
                $firstActive = null;
                foreach ($user->getTwoFactorMethods() as $existingMethod) {
                    if ($existingMethod->getMethod() !== $method && $existingMethod->isActive()) {
                        if (null === $firstActive) {
                            $firstActive = $existingMethod;
                        }
                        if ($existingMethod->isDefault()) {
                            $hasActiveDefault = true;
                            break;
                        }
                    }
                }
                if (!$hasActiveDefault && null !== $firstActive) {
                    $firstActive->setIsDefault(true);
                }
            }
        }

        $this->entityManager->flush();
    }

    /**
     * @param User $user
     * @param TwoFactorMethodEnum $method
     * @return void
     * @throws BadRequestHttpException
     */
    public function setDefaultTwoFactorMethod(User $user, TwoFactorMethodEnum $method): void
    {
        if ($method === TwoFactorMethodEnum::BACKUP_CODE) {
            throw new BadRequestHttpException($this->translator->trans('user.two_factor.invalid_method', [], 'validators'));
        }

        $targetMethod = null;
        foreach ($user->getTwoFactorMethods() as $existingMethod) {
            if ($existingMethod->getMethod() === $method && $existingMethod->isActive()) {
                $targetMethod = $existingMethod;
                break;
            }
        }

        if (null === $targetMethod) {
            throw new BadRequestHttpException($this->translator->trans('user.two_factor.method_not_enabled', [], 'validators'));
        }

        foreach ($user->getTwoFactorMethods() as $existingMethod) {
            $existingMethod->setIsDefault($existingMethod === $targetMethod);
        }

        $this->entityManager->flush();
    }

    /**
     * @param User $user
     * @param string $password
     * @return array<string>
     * @throws BadRequestHttpException
     * @throws TooManyRequestsHttpException
     */
    public function generateBackupCodes(User $user, string $password): array
    {
        $this->verifyUserPassword($user, $password);

        if (!$user->has2faEnabled()) {
            throw new BadRequestHttpException($this->translator->trans('user.two_factor.method_not_enabled', [], 'validators'));
        }

        return $this->twoFactorVerifier->generateBackupCodes($user);
    }

    /**
     * @param User $user
     * @param string $password
     * @return void
     * @throws BadRequestHttpException
     * @throws TooManyRequestsHttpException
     */
    private function verifyUserPassword(User $user, string $password): void
    {
        if (null === $user->getPassword()) {
            return;
        }

        $limiter = $this->twoFactorAuthLimiter->create((string) $user->getEmail());
        if (false === $limiter->consume(1)->isAccepted()) {
            throw new TooManyRequestsHttpException();
        }

        if (!$password || !$this->passwordHasher->isPasswordValid($user, $password)) {
            throw new BadRequestHttpException($this->translator->trans('user.invalid_password', [], 'validators'));
        }

        $limiter->reset();
    }
}
