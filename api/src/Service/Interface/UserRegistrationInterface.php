<?php

namespace App\Service\Interface;

use App\Entity\User;

interface UserRegistrationInterface
{
    /**
     * @param User $user
     * @param array{firstName?: string, lastName?: string} $profileData
     * @param bool $isVerified
     */
    public function registerUser(User $user, array $profileData = [], bool $isVerified = false): User;

    /**
     * @param string $email
     * @return User|null
     */
    public function restoreUser(string $email): ?User;

    /**
     * @param string $email
     * @return bool
     */
    public function isAccountSoftDeleted(string $email): bool;
}
