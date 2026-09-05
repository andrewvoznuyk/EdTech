<?php

namespace App\MessageHandler;

use App\Message\ExampleNotificationMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ExampleNotificationHandler
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(ExampleNotificationMessage $message): void
    {
        $this->logger->info(sprintf(
            '[Messenger Async Worker] Successfully processed message for %s: %s',
            $message->getRecipientEmail(),
            $message->getContent()
        ));
    }
}
