<?php

namespace App\Tests\Unit\MessageHandler;

use App\Entity\User;
use App\Message\ExampleNotificationMessage;
use App\MessageHandler\ExampleNotificationHandler;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ExampleNotificationHandlerTest extends TestCase
{
    /**
     * @return void
     */
    public function testInvokeProcessesMessage(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $userRepository = $this->createMock(UserRepository::class);

        $user = new User();
        $user->setEmail('worker@example.com');

        $entityManager->method('getRepository')->with(User::class)->willReturn($userRepository);
        $userRepository->method('findOneBy')->with(['email' => 'worker@example.com'])->willReturn($user);

        $logger->expects($this->once())
            ->method('info')
            ->with($this->stringContains('Successfully processed message for worker@example.com'));

        $handler = new ExampleNotificationHandler($logger, $entityManager);
        $message = new ExampleNotificationMessage('Test notification', 'worker@example.com', $user);

        $handler($message);
    }
}
