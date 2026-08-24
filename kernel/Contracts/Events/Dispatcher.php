<?php

namespace MacropaySolutions\Kernel\Contracts\Events;

use MacropaySolutions\Kernel\Events\QueuedCallable;

interface Dispatcher
{
    /**
     * Register an event listener with the dispatcher.
     */
    public function listen(
        string|array $events,
        string|array|QueuedCallable|null $listener = null
    ): void;

    /**
     * Determine if a given event has listeners.
     *
     * @param string $eventName
     * @return bool
     */
    public function hasListeners($eventName);

    /**
     * Dispatch an event until the first non-null response is returned.
     *
     * @param string|object $event
     * @param mixed $payload
     * @return mixed
     */
    public function until($event, $payload = []);

    /**
     * Dispatch an event and call the listeners.
     *
     * @param string|object $event
     * @param mixed $payload
     * @param bool $halt
     * @return array|null
     */
    public function dispatch($event, $payload = [], $halt = false);

    /**
     * Remove a set of listeners from the dispatcher.
     *
     * @param string $event
     * @return void
     */
    public function forget($event);
}
