<?php

namespace App\Message;

class ExampleNotificationMessage implements AsyncMessageInterface
{
    public function __construct(
        private readonly string $content,
        private readonly string $recipientEmail
    ) {
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getRecipientEmail(): string
    {
        return $this->recipientEmail;
    }
}
