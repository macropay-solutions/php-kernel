<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Container;

use MacropaySolutions\Kernel\Container\Container;

/**
 * A container with circular dependency detection on, as with app.circular_dependency_memory_limit outside production.
 */
class DetectingContainer extends Container
{
    protected static int $circularDependencyMemoryLimit = 10485760;
}
