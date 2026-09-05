<?php

namespace App\Command;

use App\Message\ExampleNotificationMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:test-dispatch',
    description: 'Dispatches a test async message to the Messenger queue.'
)]
class TestDispatchCommand extends Command
{
    public function __construct(
        private readonly MessageBusInterface $bus
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $message = new ExampleNotificationMessage('Hello from FrankenPHP & Symfony Messenger!', 'test@example.com');
        $this->bus->dispatch($message);

        $output->writeln('<info>Successfully dispatched ExampleNotificationMessage to async transport!</info>');

        return Command::SUCCESS;
    }
}
