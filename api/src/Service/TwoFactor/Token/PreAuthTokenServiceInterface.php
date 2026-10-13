<?php

namespace App\Service\TwoFactor\Token;

use App\Entity\User;

interface PreAuthTokenServiceInterface
{
    /**
     * @param User $user
     * @return string
     */
    public function createToken(User $user): string;

    /**
     * @param string $token
     * @return User
     */
    public function validateToken(string $token): User;

    /**
     * @param string $token
     * @return void
     */
    public function consumeToken(string $token): void;
}
