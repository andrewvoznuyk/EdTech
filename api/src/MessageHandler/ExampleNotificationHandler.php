<?php

namespace App\MessageHandler;

use App\Entity\User;
use App\Message\ExampleNotificationMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class ExampleNotificationHandler
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(ExampleNotificationMessage $message): void
    {

        $userData = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $message->getUser()->getEmail()]);
        $this->logger->info(sprintf(
            '[Messenger Async Worker] Successfully processed message for %s: %s',
            $message->getRecipientEmail(),
            $message->getContent()
        ));
    }
}
