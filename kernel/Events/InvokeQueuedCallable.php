<?php

namespace MacropaySolutions\Kernel\Events;

use MacropaySolutions\Kernel\Queue\CallQueuedCallable;

class InvokeQueuedCallable
{
    /**
     * Handle the event.
     */
    public function handle(array $callable, array $arguments): void
    {
        CallQueuedCallable::invoke(
            [$callable[0], $callable[1]],
            $arguments, /** should be assoc array @see \MacropaySolutions\Kernel\Queue\Queue::storableCallable */
            $callable[2] ?? []
        );
    }

    /**
     * Handle a job failure.
     */
    public function failed(array $callable, array $arguments, array $catchCallbacks, \Throwable $exception): void
    {
        foreach ($catchCallbacks as $callback) {
            /** $arguments should be assoc array @see \MacropaySolutions\Kernel\Queue\Queue::storableCallable */
            CallQueuedCallable::invokeFailure($callback, $exception, $arguments);
        }
    }
}
