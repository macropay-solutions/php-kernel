<?php

namespace Illuminate\Events;

use Closure;

if (!function_exists('Illuminate\Events\queueable')) {
    /**
     * Create a new queued Closure event listener.
     *
     * @param \Closure $closure
     * @return \Illuminate\Events\QueuedClosure
     */
    function queueable(Closure $closure)
    {
        return new QueuedClosure($closure);
    }

    /**
     * Create a new queued Callable event listener.
     */
    function queueableArray(array $callable): QueuedCallable
    {
        return new QueuedCallable($callable);
    }
}
