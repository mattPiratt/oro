<?php

declare(strict_types=1);

namespace Tests\Bundle\ChainCommandBundle\DependencyInjection\Compiler;

use ChainCommandBundle\DependencyInjection\Compiler\ChainCommandCompilerPass;
use ChainCommandBundle\Service\ChainCommandRegistry;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use stdClass;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class ChainCommandCompilerPassTest extends TestCase
{
    private ContainerBuilder $container;
    private ChainCommandCompilerPass $compilerPass;
    private Definition $registryDefinition;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->compilerPass = new ChainCommandCompilerPass();

        // Set up registry definition
        $this->registryDefinition = new Definition(ChainCommandRegistry::class);
        $this->container->setDefinition(ChainCommandRegistry::class, $this->registryDefinition);
    }

    public function testProcessWithNoTaggedServices(): void
    {
        // When processing with no tagged services
        $this->compilerPass->process($this->container);

        // Then no method calls should be added to the registry
        $this->assertCount(0, $this->registryDefinition->getMethodCalls());
    }

    public function testProcessWithValidTaggedServices(): void
    {
        // Given a properly tagged command service
        $commandDefinition = new Definition(TestCommand::class);
        $commandDefinition->addTag(ChainCommandCompilerPass::CHAIN_MEMBER_TAG, [
            ChainCommandCompilerPass::MAIN_COMMAND_ATTRIBUTE => 'main:command'
        ]);
        $this->container->setDefinition('test.command', $commandDefinition);

        // When processing the container
        $this->compilerPass->process($this->container);

        // Then the registry should have an addMemberCommand call
        $methodCalls = $this->registryDefinition->getMethodCalls();
        $this->assertCount(1, $methodCalls);
        $this->assertEquals('addMemberCommand', $methodCalls[0][0]);
        $this->assertEquals('main:command', $methodCalls[0][1][0]);
        $this->assertEquals('test:command', $methodCalls[0][1][1]);
    }

    public function testProcessWithMissingRegistry(): void
    {
        // Given a container without the registry service
        $container = new ContainerBuilder();
        $commandDefinition = new Definition(TestCommand::class);
        $commandDefinition->addTag(ChainCommandCompilerPass::CHAIN_MEMBER_TAG, [
            ChainCommandCompilerPass::MAIN_COMMAND_ATTRIBUTE => 'main:command'
        ]);
        $container->setDefinition('test.command', $commandDefinition);

        // When processing the container
        $this->compilerPass->process($container);

        // Then no exception should be thrown (the process should silently skip)
        $this->assertTrue(true); // Just to assert something
    }

    public function testProcessWithInvalidTaggedService(): void
    {
        // Given a tagged service that is not a command
        $notCommandDefinition = new Definition(stdClass::class);
        $notCommandDefinition->addTag(ChainCommandCompilerPass::CHAIN_MEMBER_TAG, [
            ChainCommandCompilerPass::MAIN_COMMAND_ATTRIBUTE => 'main:command'
        ]);
        $this->container->setDefinition('not.command', $notCommandDefinition);

        // Then an exception should be thrown when processing
        $this->expectException(InvalidConfigurationException::class);
        $this->compilerPass->process($this->container);
    }

    public function testProcessWithMissingMainCommandAttribute(): void
    {
        // Given a command service with a tag missing the main_command attribute
        $commandDefinition = new Definition(TestCommand::class);
        $commandDefinition->addTag(ChainCommandCompilerPass::CHAIN_MEMBER_TAG, []);
        $this->container->setDefinition('test.command', $commandDefinition);

        // Then an exception should be thrown when processing
        $this->expectException(InvalidConfigurationException::class);
        $this->compilerPass->process($this->container);
    }

    public function testProcessWithEmptyMainCommandAttribute(): void
    {
        // Given a command service with an empty main_command attribute
        $commandDefinition = new Definition(TestCommand::class);
        $commandDefinition->addTag(ChainCommandCompilerPass::CHAIN_MEMBER_TAG, [
            ChainCommandCompilerPass::MAIN_COMMAND_ATTRIBUTE => ''
        ]);
        $this->container->setDefinition('test.command', $commandDefinition);

        // Then an exception should be thrown when processing
        $this->expectException(InvalidConfigurationException::class);
        $this->compilerPass->process($this->container);
    }

    public function testProcessWithNonStringMainCommandAttribute(): void
    {
        // Given a command service with a non-string main_command attribute
        $commandDefinition = new Definition(TestCommand::class);
        $commandDefinition->addTag(ChainCommandCompilerPass::CHAIN_MEMBER_TAG, [
            ChainCommandCompilerPass::MAIN_COMMAND_ATTRIBUTE => 123 // Non-string value
        ]);
        $this->container->setDefinition('test.command', $commandDefinition);

        // Then an exception should be thrown when processing
        $this->expectException(InvalidConfigurationException::class);
        $this->compilerPass->process($this->container);
    }

    /**
     * @dataProvider getCommandNameDataProvider
     */
    public function testGetCommandName(string $class, ?string $expectedName): void
    {
        // Create a reflection method to access the private getCommandName method
        $reflectionClass = new ReflectionClass(ChainCommandCompilerPass::class);
        $method = $reflectionClass->getMethod('getCommandName');

        // Create definition with the class
        $definition = new Definition($class);

        // Call the method using reflection
        $result = $method->invoke($this->compilerPass, $definition, 'test.service');

        // Assert the expected result
        $this->assertSame($expectedName, $result);
    }

    public function getCommandNameDataProvider(): array
    {
        return [
            'Command with AsCommand attribute' => [
                TestCommand::class,
                'test:command'
            ],
            'Command without name attribute' => [
                CommandWithoutName::class,
                null
            ],
            'Not a command class' => [
                stdClass::class,
                null
            ],
            'Command with empty name' => [
                CommandWithEmptyName::class,
                null
            ],
        ];
    }

    public function testGetCommandNameWithNonExistentClass(): void
    {
        // Create a reflection method to access the private getCommandName method
        $reflectionClass = new ReflectionClass(ChainCommandCompilerPass::class);
        $method = $reflectionClass->getMethod('getCommandName');

        // Create definition with a non-existent class
        $definition = new Definition('NonExistentClass');

        // Call the method using reflection
        $result = $method->invoke($this->compilerPass, $definition, 'test.service');

        // Should return null for non-existent classes
        $this->assertNull($result);
    }
}

#[AsCommand(name: 'test:command')]
class TestCommand extends Command
{
}

class CommandWithoutName extends Command
{
}

#[AsCommand(name: '')]
class CommandWithEmptyName extends Command
{
}
