<?php

declare(strict_types=1);

namespace BarBundle\Command;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A simple demo command
 * Outputs "Hi from Bar!"
 */
#[AsCommand(name: 'bar:hi', description: 'Says hi from Bar')]
class HiCommand extends Command
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $message = 'Hi from Bar!';
        $output->writeln($message);
        $this->logger->info($message);

        return Command::SUCCESS;
    }
}
