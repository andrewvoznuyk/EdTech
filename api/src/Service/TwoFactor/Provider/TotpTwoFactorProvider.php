<?php

namespace App\Service\TwoFactor\Provider;

use App\Entity\User;
use App\Enum\TwoFactorMethodEnum;
use App\Service\Security\Encryption\SecretCipherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Exception\ValidationException;
use Endroid\QrCode\Writer\PngWriter;
use OTPHP\TOTP;
use Throwable;

readonly class TotpTwoFactorProvider implements TotpTwoFactorProviderInterface
{
    private const string ISSUER = 'Perevireno';
    private const int TIME_WINDOW_LEEWAY = 1;

    /**
     * @param SecretCipherInterface $secretCipher
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(
        private SecretCipherInterface $secretCipher,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return TwoFactorMethodEnum
     */
    public function getName(): TwoFactorMethodEnum
    {
        return TwoFactorMethodEnum::TOTP;
    }

    /**
     * @param User $user
     * @return void
     */
    public function prepareAuthentication(User $user): void
    {
    }

    /**
     * @param User $user
     * @param string $code
     * @return bool
     */
    public function validateAuthentication(User $user, string $code): bool
    {
        $cleanCode = str_replace([' ', '-'], '', trim($code));
        if ('' === $cleanCode) {
            return false;
        }

        $totpMethod = null;
        foreach ($user->getTwoFactorMethods() as $method) {
            if ($method->getMethod() === TwoFactorMethodEnum::TOTP && $method->isActive()) {
                $totpMethod = $method;
                break;
            }
        }

        if (null === $totpMethod || null === $totpMethod->getSecret()) {
            return false;
        }

        try {
            $plainSecret = $this->secretCipher->decrypt($totpMethod->getSecret());
            $totp = TOTP::createFromSecret($plainSecret);
        } catch (Throwable) {
            return false;
        }

        $period = $totp->getPeriod();
        $now = time();
        $matchedSlice = null;

        foreach ([0, self::TIME_WINDOW_LEEWAY, -self::TIME_WINDOW_LEEWAY] as $offset) {
            $timestamp = $now + ($offset * $period);
            if (hash_equals($totp->at($timestamp), $cleanCode)) {
                $matchedSlice = intdiv($timestamp, $period);
                break;
            }
        }

        if (null === $matchedSlice) {
            return false;
        }

        if (null !== $totpMethod->getLastUsedSlice() && $matchedSlice <= $totpMethod->getLastUsedSlice()) {
            return false;
        }

        $totpMethod->setLastUsedSlice($matchedSlice);
        $this->entityManager->flush();

        return true;
    }

    /**
     * @return string
     */
    public function generateSecret(): string
    {
        $totp = TOTP::generate();

        return $totp->getSecret();
    }

    /**
     * @param string $secret
     * @param string $accountName
     * @return string
     * @throws ValidationException
     */
    public function generateQrCodeDataUri(string $secret, string $accountName): string
    {
        $totp = TOTP::createFromSecret($secret);
        $totp->setLabel($accountName);
        $totp->setIssuer(self::ISSUER);

        $builder = new Builder(
            writer: new PngWriter(),
            data: $totp->getProvisioningUri(),
            size: 200,
            margin: 10
        );

        return $builder->build()->getDataUri();
    }

    /**
     * @param string $secret
     * @param string $code
     * @return bool
     */
    public function verifyCode(string $secret, string $code): bool
    {
        $cleanCode = str_replace([' ', '-'], '', trim($code));
        if ('' === $cleanCode) {
            return false;
        }

        $totp = TOTP::createFromSecret($secret);

        return $totp->verify($cleanCode, null, self::TIME_WINDOW_LEEWAY);
    }
}
