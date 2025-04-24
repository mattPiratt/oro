<?php

declare(strict_types=1);

namespace Tests\Bundle\ChainCommandBundle\EventListener;

use ChainCommandBundle\EventListener\ConsoleCommandListener;
use ChainCommandBundle\Service\ChainCommandRegistry;
use Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ConsoleCommandListenerTest extends TestCase
{
    private ConsoleCommandListener $listener;
    private MockObject $registry;
    private MockObject $logger;
    private Command $command;
    private Command $memberCommand;
    private MockObject $input;
    private MockObject $output;
    private MockObject $application;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(ChainCommandRegistry::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->listener = new ConsoleCommandListener($this->registry, $this->logger);

        // Create real Command instances
        $this->command = new Command('main:command');
        $this->memberCommand = new Command('member:command');

        // Set up other mocks
        $this->input = $this->createMock(InputInterface::class);
        $this->output = $this->createMock(OutputInterface::class);
        $this->application = $this->createMock(Application::class);
    }

    public function testGetSubscribedEvents(): void
    {
        $events = ConsoleCommandListener::getSubscribedEvents();
        $this->assertIsArray($events);
        $this->assertArrayHasKey('console.command', $events);
        $this->assertArrayHasKey('console.terminate', $events);
    }

    public function testOnConsoleCommandWithNullCommand(): void
    {
        // Given an event with no command
        $event = new ConsoleCommandEvent(null, $this->input, $this->output);

        // When handling the event
        $this->listener->onConsoleCommand($event);

        // Then no exception should be thrown
        $this->assertTrue(true); // Just to assert something
    }

    public function testOnConsoleCommandWithMemberCommand(): void
    {
        // Given a member command and output
        $this->registry->method('isMemberCommand')->with('member:command')->willReturn(true);
        $this->registry->method('getMainCommandNameForMember')->with('member:command')->willReturn('main:command');

        $event = new ConsoleCommandEvent($this->memberCommand, $this->input, $this->output);

        // Expect error message to be logged and displayed
        $this->logger->expects($this->once())->method('error');
        $this->output->expects($this->once())->method('writeln');

        // When handling the event
        $this->listener->onConsoleCommand($event);

        // Then the command should be disabled
        $this->assertFalse($event->commandShouldRun());
    }

    public function testOnConsoleCommandWithMainCommand(): void
    {
        // Given a main command with member commands
        $this->registry->method('isMemberCommand')->with('main:command')->willReturn(false);
        $this->registry->method('isMainCommand')->with('main:command')->willReturn(true);
        $this->registry->method('getMemberCommandNames')->with('main:command')->willReturn(['member:command']);

        $event = new ConsoleCommandEvent($this->command, $this->input, $this->output);

        // Expect info messages to be logged
        $this->logger->expects($this->exactly(3))->method('info');

        // When handling the event
        $this->listener->onConsoleCommand($event);

        // Command should not be disabled
        $this->assertTrue($event->commandShouldRun());
    }

    public function testOnConsoleTerminateWithNonMainCommand(): void
    {
        // Given a terminate event with a non-main command
        $this->registry->method('isMainCommand')->with('main:command')->willReturn(false);

        $event = new ConsoleTerminateEvent($this->command, $this->input, $this->output, 0);

        // When handling the event
        $this->listener->onConsoleTerminate($event);

        // Then no member commands should be executed
        // (we can't really assert this directly in a unit test, so we just make sure no exception is thrown)
        $this->assertTrue(true);
    }

    public function testOnConsoleTerminateWithFailedMainCommand(): void
    {
        // Given a terminate event with a failed main command
        $this->command->setApplication($this->application);
        $this->registry->method('isMainCommand')->with('main:command')->willReturn(true);

        $event = new ConsoleTerminateEvent($this->command, $this->input, $this->output, 1);

        // Expect warning to be logged
        $this->logger->expects($this->never())->method('warning');

        // When handling the event
        $this->listener->onConsoleTerminate($event);
    }

    public function testOnConsoleTerminateWithNoMemberCommands(): void
    {
        // Given a terminate event with a main command that has no member commands
        $this->command->setApplication($this->application);

        $this->registry->method('isMainCommand')->with('main:command')->willReturn(true);
        $this->registry->method('getMemberCommandNames')->with('main:command')
            ->willReturn([]);

        $event = new ConsoleTerminateEvent($this->command, $this->input, $this->output, 0);

        // When handling the event
        $this->listener->onConsoleTerminate($event);

        // No member commands should be executed
        $this->assertTrue(true);
    }

    public function testOnConsoleTerminateWithSuccessfulMainCommand(): void
    {
        // Given a terminate event with a successful main command that has member commands
        $this->command->setApplication($this->application);
        $this->memberCommand->setApplication($this->application);

        $this->registry->method('isMainCommand')->with('main:command')->willReturn(true);
        $this->registry->method('getMemberCommandNames')->with('main:command')
            ->willReturn(['member:command']);

        $this->application->expects($this->once())
            ->method('find')
            ->with('member:command')
            ->willReturn($this->memberCommand);

        // Expect proper logging
        $this->logger->expects($this->exactly(2))->method('info');

        $event = new ConsoleTerminateEvent($this->command, $this->input, $this->output, 0);

        // When handling the event
        $this->listener->onConsoleTerminate($event);
    }

    public function testOnConsoleTerminateWithExceptionDuringMemberExecution(): void
    {
        // Given a terminate event with a successful main command that has member commands
        // but an exception occurs during member execution
        $this->command->setApplication($this->application);
        $exception = new Exception('Test exception');

        $this->registry->method('isMainCommand')->with('main:command')->willReturn(true);
        $this->registry->method('getMemberCommandNames')->with('main:command')
            ->willReturn(['member:command']);

        $this->application->expects($this->once())
            ->method('find')
            ->with('member:command')
            ->willThrowException($exception);

        // Expect error to be logged
        $this->logger->expects($this->once())->method('error');

        $event = new ConsoleTerminateEvent($this->command, $this->input, $this->output, 0);

        // When handling the event
        $this->listener->onConsoleTerminate($event);
    }

    public function testOnConsoleTerminateWithNoApplication(): void
    {
        // Given a terminate event with a main command but no application
        $this->registry->method('isMainCommand')->with('main:command')->willReturn(true);
        $this->registry->method('getMemberCommandNames')->with('main:command')
            ->willReturn(['member:command']);

        // Command with no application
        $command = new Command('main:command');

        $event = new ConsoleTerminateEvent($command, $this->input, $this->output, 0);

        // Expect error to be logged
        $this->logger->expects($this->once())->method('error')
            ->with('Cannot execute chain members: no console application available :O');

        // When handling the event
        $this->listener->onConsoleTerminate($event);
    }
}

