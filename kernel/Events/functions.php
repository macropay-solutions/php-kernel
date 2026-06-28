<?php

namespace MacropaySolutions\Kernel\Events;

if (!function_exists('MacropaySolutions\Kernel\Events\queueable')) {
    /**
     * Create a new queued Callable event listener.
     */
    function queueableArray(array $callable): QueuedCallable
    {
        return new QueuedCallable($callable);
    }
}
