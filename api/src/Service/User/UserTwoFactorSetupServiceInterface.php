<?php

namespace App\Service\User;

use App\Entity\User;
use App\Enum\TwoFactorMethodEnum;

interface UserTwoFactorSetupServiceInterface
{
    /**
     * @param User $user
     * @param bool $makeDefault
     * @return void
     */
    public function enableEmailTwoFactor(User $user, bool $makeDefault = false): void;

    /**
     * @param User $user
     * @param string $code
     * @return array<string, mixed>
     */
    public function confirmEmailTwoFactor(User $user, string $code): array;

    /**
     * @param User $user
     * @return array{secret: string, qr_code: string}
     */
    public function setupTotpTwoFactor(User $user): array;

    /**
     * @param User $user
     * @param string $code
     * @return array<string, mixed>
     */
    public function confirmTotpTwoFactor(User $user, string $code): array;

    /**
     * @param User $user
     * @param string $password
     * @param TwoFactorMethodEnum|null $method
     * @return void
     */
    public function disableTwoFactor(User $user, string $password, ?TwoFactorMethodEnum $method = null): void;

    /**
     * @param User $user
     * @param TwoFactorMethodEnum $method
     * @return void
     */
    public function setDefaultTwoFactorMethod(User $user, TwoFactorMethodEnum $method): void;

    /**
     * @param User $user
     * @param string $password
     * @return array<string>
     */
    public function generateBackupCodes(User $user, string $password): array;
}
