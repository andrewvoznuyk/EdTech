<?php

namespace App\Service\Interface;

use App\Entity\User;

interface UserAwareInterface
{
    /**
     * @return User|null
     */
    public function getUser(): ?User;

    /**
     * @param User|null $user
     * @return self
     */
    public function setUser(?User $user): self;
}
