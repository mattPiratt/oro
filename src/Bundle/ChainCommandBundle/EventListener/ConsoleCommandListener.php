<?php

declare(strict_types=1);

namespace ChainCommandBundle\EventListener;

use ChainCommandBundle\Service\ChainCommandRegistry;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event listener for console events
 * Prevents direct execution of chain-member commands and executes chain members after the main command
 */
class ConsoleCommandListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly ChainCommandRegistry $registry,
        private readonly LoggerInterface $logger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::COMMAND => ['onConsoleCommand', 100],
            ConsoleEvents::TERMINATE => ['onConsoleTerminate', -100],
        ];
    }

    /**
     * Prevents execution of member commands
     * Marks main commands for chain handling
     */
    public function onConsoleCommand(ConsoleCommandEvent $event): void
    {
        $command = $event->getCommand();
        if (!$command) {
            return;
        }

        $commandName = $command->getName();
        if (!$commandName) {
            return;
        }

        // Check if this is a member command - prevent direct execution
        if ($this->registry->isMemberCommand($commandName)) {
            $mainCommandName = $this->registry->getMainCommandNameForMember($commandName);
            $errorMsg = sprintf(
                'Error: %s command is a member of %s command chain and cannot be executed on its own.',
                $commandName,
                $mainCommandName
            );

            $event->getOutput()->writeln("<error>$errorMsg</error>");
            $this->logger->error($errorMsg);

            // Disable the command - this will stop execution
            $event->disableCommand();

            return;
        }

        // Check if this is a main command that has a chain
        if ($this->registry->isMainCommand($commandName)) {
            $memberCommands = $this->registry->getMemberCommandNames($commandName);
            $this->logger->info(
                sprintf(
                    '%s is a master command of a command chain that has registered member commands',
                    $commandName
                )
            );

            foreach ($memberCommands as $memberCommand) {
                $this->logger->info(
                    sprintf(
                        '%s registered as a member of %s command chain',
                        $memberCommand,
                        $commandName
                    )
                );
            }

            $this->logger->info(sprintf('Executing %s command itself first:', $commandName));
        }
    }

    /**
     * Executes any member commands in the chain after the main command completes
     */
    public function onConsoleTerminate(ConsoleTerminateEvent $event): void
    {
        $command = $event->getCommand();

        $commandName = $command->getName();
        if (!$commandName) {
            return;
        }

        // Check if this was a main command with a chain
        if ($this->registry->isMainCommand($commandName)) {
            $memberCommands = $this->registry->getMemberCommandNames($commandName);
            if (empty($memberCommands)) {
                return;
            }

            $this->logger->info(sprintf('Executing %s chain members:', $commandName));

            $application = $command->getApplication();
            if (!$application) {
                $this->logger->error('Cannot execute chain members: no console application available :O');
                return;
            }

            $output = $event->getOutput();

            // Execute each member command
            foreach ($memberCommands as $memberCommandName) {
                try {
                    $memberCommand = $application->find($memberCommandName);

                    $memberInput = new ArrayInput([]);
                    $memberInput->setInteractive(false);

                    $memberCommand->run($memberInput, $output);
                } catch (Exception $e) {
                    $errorMsg = sprintf(
                        'Error executing chain member %s: %s',
                        $memberCommandName,
                        $e->getMessage()
                    );
                    $this->logger->error($errorMsg);
                }
            }

            $this->logger->info(sprintf('Execution of %s chain completed.', $commandName));
        }
    }
}
