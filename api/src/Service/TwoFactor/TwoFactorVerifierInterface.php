<?php

namespace App\Service\TwoFactor;

use App\Entity\User;
use App\Enum\TwoFactorMethodEnum;

interface TwoFactorVerifierInterface
{
    /**
     * @param User $user
     * @param TwoFactorMethodEnum $method
     * @return void
     */
    public function prepare(User $user, TwoFactorMethodEnum $method): void;

    /**
     * @param User $user
     * @param string $code
     * @param TwoFactorMethodEnum $method
     * @return bool
     */
    public function validate(User $user, string $code, TwoFactorMethodEnum $method): bool;

    /**
     * @param User $user
     * @param string $code
     * @return bool
     */
    public function validateBackupCode(User $user, string $code): bool;

    /**
     * @param User $user
     * @return array<string>
     */
    public function generateBackupCodes(User $user): array;
}
