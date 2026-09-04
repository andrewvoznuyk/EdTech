<?php

namespace App\Service\TwoFactor\Provider;

use App\Entity\User;
use App\Enum\TwoFactorMethodEnum;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.two_factor_provider')]
interface TwoFactorProviderInterface
{
    /**
     * @return TwoFactorMethodEnum
     */
    public function getName(): TwoFactorMethodEnum;

    /**
     * @param User $user
     * @return void
     */
    public function prepareAuthentication(User $user): void;

    /**
     * @param User $user
     * @param string $code
     * @return bool
     */
    public function validateAuthentication(User $user, string $code): bool;
}
