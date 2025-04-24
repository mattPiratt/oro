<?php

declare(strict_types=1);

namespace Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\ApplicationTester;

class ChainCommandTest extends KernelTestCase
{
    private ApplicationTester $tester;

    protected function setUp(): void
    {
        self::bootKernel();

        $application = new Application(self::$kernel);
        $application->setAutoExit(false);

        $this->tester = new ApplicationTester($application);
    }

    public function testMainCommandExecutesChainMembers(): void
    {
        // When running the main command through the application
        $exitCode = $this->tester->run(['command' => 'foo:hello']);

        // Then it should execute successfully
        self::assertEquals(Command::SUCCESS, $exitCode);

        // Get the output
        $display = $this->tester->getDisplay();

        // The output should contain the main command output
        self::assertStringContainsString('Hello from Foo!', $display);

        // The output should also contain the member command output
        // This verifies that the chain command listener is working properly
        self::assertStringContainsString('Hi from Bar!', $display);
    }

    public function testMemberCommandCannotBeExecutedDirectly(): void
    {
        // When running a chain member command directly
        $exitCode = $this->tester->run(['command' => 'bar:hi']);

        // 113 is the exit code for a command that is disabled
        // https://symfony.com/doc/6.3/components/console/events.html#disable-commands-inside-listeners
        self::assertEquals(113, $exitCode);

        $display = $this->tester->getDisplay();

        // The output should contain the error message
        self::assertStringContainsString(
            'Error: bar:hi command is a member of foo:hello command chain and cannot be executed on its own.',
            $display
        );

        // And it should NOT contain the actual command output
        self::assertStringNotContainsString('Hi from Bar!', $display);
    }

    public function testRegularCommandIsExecutedNormally(): void
    {
        $exitCode = $this->tester->run(['command' => 'debug:config']);

        self::assertEquals(Command::SUCCESS, $exitCode);
        $display = $this->tester->getDisplay();

        // And only its output should be present
        self::assertStringContainsString('Available registered bundles', $display);
    }
}
