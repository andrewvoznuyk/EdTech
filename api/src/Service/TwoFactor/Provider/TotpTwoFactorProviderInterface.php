<?php

namespace App\Service\TwoFactor\Provider;

interface TotpTwoFactorProviderInterface extends TwoFactorProviderInterface
{
    /**
     * @return string
     */
    public function generateSecret(): string;

    /**
     * @param string $secret
     * @param string $accountName
     * @return string
     */
    public function generateQrCodeDataUri(string $secret, string $accountName): string;

    /**
     * @param string $secret
     * @param string $code
     * @return bool
     */
    public function verifyCode(string $secret, string $code): bool;
}
