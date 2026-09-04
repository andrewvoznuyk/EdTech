<?php

namespace App\Event;

use App\Entity\User;
use Symfony\Contracts\EventDispatcher\Event;

class GoogleUserAuthenticatedEvent extends Event
{
    /**
     * @var User|null
     */
    private ?User $user = null;

    /**
     * @param string $email
     * @param array $payload
     */
    public function __construct(
        private readonly string $email,
        private readonly array $payload
    ) {}

    /**
     * @return string
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * @return array
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * @return array{firstName: string, lastName: string}
     */
    public function getProfileData(): array
    {
        return [
            'firstName' => (string) ($this->payload['given_name'] ?? ''),
            'lastName'  => (string) ($this->payload['family_name'] ?? ''),
        ];
    }

    /**
     * @return User|null
     */
    public function getUser(): ?User
    {
        return $this->user;
    }

    /**
     * @param User $user
     * @return void
     */
    public function setUser(User $user): void
    {
        $this->user = $user;
    }
}
