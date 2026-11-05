<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures;

use MacropaySolutions\Kernel\Container\Container;
use MacropaySolutions\Kernel\Queue\CallQueuedCallable;
use MacropaySolutions\Kernel\Queue\CallQueuedHandler;

/**
 * Restores the queued command from a payload the same way the worker does.
 */
class RestoringCallQueuedHandler extends CallQueuedHandler
{
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function restore(array $payload): CallQueuedCallable
    {
        return $this->getCommand($payload['data']);
    }
}
