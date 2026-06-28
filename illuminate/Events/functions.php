<?php

namespace Illuminate\Events;

if (!function_exists('Illuminate\Events\queueable')) {
    /**
     * Create a new queued Callable event listener.
     */
    function queueableArray(array $callable): QueuedCallable
    {
        return new QueuedCallable($callable);
    }
}
