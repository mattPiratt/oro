<?php

declare(strict_types=1);

namespace Tests\Bundle\ChainCommandBundle\DependencyInjection;

use ChainCommandBundle\DependencyInjection\ChainCommandExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ChainCommandExtensionTest extends TestCase
{
    private ContainerBuilder $container;
    private ChainCommandExtension $extension;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->extension = new ChainCommandExtension();
    }

    public function testLoad(): void
    {
        $this->extension->load([], $this->container);

        $serviceIds = $this->container->getServiceIds();

        $ourServiceId = 'ChainCommandBundle\EventListener\ConsoleCommandListener';

        // verify ConsoleCommandListener is registered
        $this->assertContains($ourServiceId, $serviceIds);

        // verify that "kernel.event_subscriber" is tagged
        $definition = $this->container->getDefinition($ourServiceId);
        $tags = $definition->getTags();
        $this->assertArrayHasKey('kernel.event_subscriber', $tags);
    }
}
