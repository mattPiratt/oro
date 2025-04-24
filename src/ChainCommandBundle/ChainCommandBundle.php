<?php

declare(strict_types=1);

namespace ChainCommandBundle;

use ChainCommandBundle\DependencyInjection\Compiler\ChainCommandCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * A bundle that provides command chaining functionality
 */
class ChainCommandBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Register the compiler pass to process ChainCommand tag members
        $container->addCompilerPass(new ChainCommandCompilerPass());
    }

}
