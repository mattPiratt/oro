<?php

declare(strict_types=1);

namespace BarBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * BarBundle - demo bundle for command chaining example
 * This bundle defines the bar:hi command which is a member of foo:hello chain
 */
class BarBundle extends Bundle
{
}
