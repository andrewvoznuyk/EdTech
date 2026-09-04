<?php

namespace App\Service\User;

use App\Entity\User;
use App\Entity\UserTwoFactorMethod;
use App\Enum\TwoFactorMethodEnum;
use Doctrine\ORM\EntityManagerInterface;


final readonly class UserTwoFactorSetupService
{
    /**
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * @param User $user
     * @param bool $makeDefault
     */
    public function enableEmailTwoFactor(User $user, bool $makeDefault = true): void
    {
        foreach ($user->getTwoFactorMethods() as $method) {
            if ($method->getMethod() === TwoFactorMethodEnum::EMAIL) {
                if ($makeDefault && !$method->isDefault()) {
                    $method->setIsDefault(true);
                    $this->entityManager->flush();
                }
                return;
            }
        }

        $userTwoFactor = new UserTwoFactorMethod();
        $userTwoFactor->setUser($user);
        $userTwoFactor->setMethod(TwoFactorMethodEnum::EMAIL);
        $userTwoFactor->setIsDefault($makeDefault);

        $this->entityManager->persist($userTwoFactor);
        $this->entityManager->flush();
    }
}
