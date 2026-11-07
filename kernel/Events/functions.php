<?php

namespace MacropaySolutions\Kernel\Events;

/**
 * Create a new queued Callable event listener.
 */
function queueableArray(array $callable): QueuedCallable
{
    return new QueuedCallable($callable);
}
