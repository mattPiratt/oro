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
    private Command $mainCommand;
    private Command $memberCommand;
    private Command $whateverCommand;
    private MockObject $input;
    private MockObject $output;
    private MockObject $application;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(ChainCommandRegistry::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->listener = new ConsoleCommandListener($this->registry, $this->logger);

        // Create real Command instances
        $this->mainCommand = new Command('main:command');
        $this->memberCommand = new Command('member:command');
        $this->whateverCommand = new Command('whatever:command');

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
        $event = new ConsoleCommandEvent(null, $this->input, $this->output);

        $this->logger
            ->expects($this->never())
            ->method('error');

        // When handling null event no exception should be thrown...
        $this->listener->onConsoleCommand($event);

        // ...but, just to assert something
        $this->assertTrue(true); //
    }

    public function testOnConsoleCommandWithEmptyCommandName(): void
    {
        // Command exists but name is not set
        $commandWithNoName = new Command();
        $event = new ConsoleCommandEvent($commandWithNoName, $this->input, $this->output);

        $this->logger
            ->expects($this->never())
            ->method('error');

        // When handling event with no name, no exception should be thrown
        $this->listener->onConsoleCommand($event);

        // Verify, the command was not disabled
        $this->assertTrue($event->commandShouldRun());
    }

    public function testOnConsoleCommandWithMemberCommand(): void
    {
        $this->registry->method('isMemberCommand')
            ->with('member:command')
            ->willReturn(true);
        $this->registry->method('getMainCommandNameForMember')
            ->with('member:command')
            ->willReturn('main:command');

        $event = new ConsoleCommandEvent($this->memberCommand, $this->input, $this->output);

        // Expect error message to be logged and displayed
        $this->logger
            ->expects($this->once())
            ->method('error');
        $this->output
            ->expects($this->once())
            ->method('writeln');

        $this->listener->onConsoleCommand($event);

        // Verify, the command was disabled
        $this->assertFalse($event->commandShouldRun());
    }

    public function testOnConsoleCommandWithMainCommand(): void
    {
        $this->registry->method('isMemberCommand')
            ->with('main:command')
            ->willReturn(false);
        $this->registry->method('isMainCommand')
            ->with('main:command')
            ->willReturn(true);
        $this->registry->method('getMemberCommandNames')
            ->with('main:command')
            ->willReturn(['member:command']);

        $event = new ConsoleCommandEvent($this->mainCommand, $this->input, $this->output);

        // Expect info messages to be logged
        $this->logger
            ->expects($this->exactly(3))
            ->method('info');

        $this->listener->onConsoleCommand($event);

        // Verify, the command was not disabled
        $this->assertTrue($event->commandShouldRun());
    }

    public function testOnConsoleTerminateWithNonMainCommand(): void
    {
        $this->registry->method('isMainCommand')
            ->with('whatever:command')
            ->willReturn(false);

        $event = new ConsoleTerminateEvent($this->whateverCommand, $this->input, $this->output, 0);

        $this->listener->onConsoleTerminate($event);

        // For not main commands, onConsoleTerminate does nothing
        // (we can't really assert this directly in a unit test, so we just make sure no exception is thrown)
        $this->assertTrue(true);
    }

    public function testOnConsoleTerminateWithNoMemberCommands(): void
    {
        $this->registry->method('isMainCommand')
            ->with('main:command')
            ->willReturn(true);
        $this->registry->method('getMemberCommandNames')
            ->with('main:command')
            ->willReturn([]);

        $event = new ConsoleTerminateEvent($this->mainCommand, $this->input, $this->output, 0);

        $this->listener->onConsoleTerminate($event);

        // For main commands with no members, onConsoleTerminate does nothing
        // (we can't really assert this directly in a unit test, so we just make sure no exception is thrown)
        $this->assertTrue(true);
    }

    public function testOnConsoleTerminateWithSuccessfulMainCommand(): void
    {
        $this->mainCommand->setApplication($this->application);
        $this->memberCommand->setApplication($this->application);

        $this->registry->method('isMainCommand')
            ->with('main:command')
            ->willReturn(true);
        $this->registry->method('getMemberCommandNames')
            ->with('main:command')
            ->willReturn(['member:command']);

        $this->application->expects($this->once())
            ->method('find')
            ->with('member:command')
            ->willReturn($this->memberCommand);

        // Expect proper logging
        $this->logger
            ->expects($this->exactly(2))
            ->method('info');

        $event = new ConsoleTerminateEvent($this->mainCommand, $this->input, $this->output, 0);

        $this->listener->onConsoleTerminate($event);
    }

    public function testOnConsoleTerminateWithExceptionDuringMemberExecution(): void
    {
        $this->mainCommand->setApplication($this->application);
        $exception = new Exception('Test exception');

        $this->registry->method('isMainCommand')
            ->with('main:command')
            ->willReturn(true);
        $this->registry->method('getMemberCommandNames')
            ->with('main:command')
            ->willReturn(['member:command']);

        $this->application->expects($this->once())
            ->method('find')
            ->with('member:command')
            ->willThrowException($exception);

        // Expect error to be logged
        $this->logger
            ->expects($this->once())
            ->method('error');

        $event = new ConsoleTerminateEvent($this->mainCommand, $this->input, $this->output, 0);

        $this->listener->onConsoleTerminate($event);
    }

    public function testOnConsoleTerminateWithNoApplication(): void
    {
        $this->registry->method('isMainCommand')->with('main:command')->willReturn(true);
        $this->registry->method('getMemberCommandNames')->with('main:command')
            ->willReturn(['member:command']);

        $event = new ConsoleTerminateEvent($this->mainCommand, $this->input, $this->output, 0);

        // Expect error to be logged
        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('Cannot execute chain members: no console application available :O');

        $this->listener->onConsoleTerminate($event);
    }

    public function testOnConsoleTerminateWithEmptyCommandName(): void
    {
        $commandWithNoName = new Command();
        $event = new ConsoleTerminateEvent($commandWithNoName, $this->input, $this->output, 0);

        // Expect error to be logged
        $this->logger
            ->expects($this->never())
            ->method('error');

        $this->listener->onConsoleTerminate($event);

        // No exception should be thrown and command should run
        $this->assertEquals(0, $event->getExitCode());
    }
}

