<?php

declare(strict_types=1);

namespace ChainCommandBundle\DependencyInjection\Compiler;

use ChainCommandBundle\Service\ChainCommandRegistry;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Takes services tagged with 'chain_command.member' to register them in the ChainCommandRegistry
 */
class ChainCommandCompilerPass implements CompilerPassInterface
{
    public const CHAIN_MEMBER_TAG = 'chain_command.member';
    public const MAIN_COMMAND_ATTRIBUTE = 'main_command';

    public function process(ContainerBuilder $container): void
    {
        if (
            !$container->hasDefinition(ChainCommandRegistry::class)
            && !$container->hasAlias(ChainCommandRegistry::class)
        ) {
            // If the registry service isn't defined, there's nothing to do.
            return;
        }

        $taggedServices = $container->findTaggedServiceIds(self::CHAIN_MEMBER_TAG);
        foreach ($taggedServices as $serviceId => $tags) {
            $memberDefinition = $container->findDefinition($serviceId);
            $memberCommandName = $this->getCommandName($memberDefinition, $serviceId);

            if ($memberCommandName === null) {
                throw new InvalidConfigurationException(
                    sprintf(
                        'Service "%s" is tagged with "%s" but does not extend "%s".',
                        $serviceId,
                        self::CHAIN_MEMBER_TAG,
                        Command::class
                    )
                );
            }

            foreach ($tags as $attributes) {
                if (!isset($attributes[self::MAIN_COMMAND_ATTRIBUTE])) {
                    throw new InvalidConfigurationException(
                        sprintf(
                            'Service "%s" is tagged with "%s" but does not have the "%s" attribute.',
                            $serviceId,
                            self::CHAIN_MEMBER_TAG,
                            self::MAIN_COMMAND_ATTRIBUTE
                        )
                    );
                }

                $mainCommandName = $attributes[self::MAIN_COMMAND_ATTRIBUTE];

                if (!is_string($mainCommandName) || empty($mainCommandName)) {
                    throw new InvalidConfigurationException(
                        sprintf(
                            'Service "%s" is tagged with "%s" but the "%s" attribute is not a valid command name.',
                            $serviceId,
                            self::CHAIN_MEMBER_TAG,
                            self::MAIN_COMMAND_ATTRIBUTE
                        )
                    );
                }

                // All good! Add method to the registry
                $registryDefinition = $container->findDefinition(ChainCommandRegistry::class);
                $registryDefinition->addMethodCall('addMemberCommand', [
                    $mainCommandName,
                    $memberCommandName,
                ]);
            }
        }
    }

    /**
     * Tries to determine the command name for a given command service definition.
     * Relies on the standard Symfony mechanisms (#[AsCommand] or $defaultName).
     */
    private function getCommandName(Definition $definition, string $serviceId): ?string
    {
        $class = $definition->getClass();
        if ($class === null || !is_subclass_of($class, Command::class)) {
            // If the class isn't set or isn't a Command, we can't determine the name.
            return null;
        }

        try {
            $reflectionClass = new ReflectionClass($class);
            $attributes = $reflectionClass->getAttributes(AsCommand::class);
            if (!empty($attributes)) {
                $instance = $attributes[0]->newInstance();
                if (
                    property_exists($instance, 'name')
                    && is_string($instance->name)
                    && !empty($instance->name)
                ) {
                    return $instance->name;
                }
            }
        } catch (ReflectionException $e) {
            // We shouldn't break the container build here
        }

        return null; // Cannot determine name
    }
}
