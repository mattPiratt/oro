<?php

declare(strict_types=1);

namespace FooBundle\Command;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A simple demo command
 * Outputs "Hello from Foo!"
 */
#[AsCommand(name: 'foo:hello', description: 'Says hello from Foo')]
class HelloCommand extends Command
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $message = 'Hello from Foo!';
        $output->writeln($message);
        $this->logger->info($message);

        return Command::SUCCESS;
    }
}
