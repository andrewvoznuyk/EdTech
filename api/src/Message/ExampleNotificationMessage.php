<?php

namespace App\Message;

use App\Entity\User;

readonly class ExampleNotificationMessage implements AsyncMessageInterface
{
    public function __construct(
        private string $content,
        private string $recipientEmail,
        private User $user,
    ) {
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getUser()
    {
        return $this->user;
    }

    public function getRecipientEmail(): string
    {
        return $this->recipientEmail;
    }
}
