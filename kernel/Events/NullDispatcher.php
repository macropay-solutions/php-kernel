<?php

namespace MacropaySolutions\Kernel\Events;

use MacropaySolutions\Kernel\Contracts\Events\Dispatcher as DispatcherContract;
use MacropaySolutions\Kernel\Support\Traits\ForwardsCalls;

class NullDispatcher implements DispatcherContract
{
    use ForwardsCalls;

    /**
     * The underlying event dispatcher instance.
     *
     * @var \MacropaySolutions\Kernel\Contracts\Events\Dispatcher
     */
    protected $dispatcher;

    /**
     * Create a new event dispatcher instance that does not fire.
     *
     * @param \MacropaySolutions\Kernel\Contracts\Events\Dispatcher $dispatcher
     * @return void
     */
    public function __construct(DispatcherContract $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * Don't fire an event.
     *
     * @param string|object $event
     * @param mixed $payload
     * @param bool $halt
     * @return void
     */
    public function dispatch($event, $payload = [], $halt = false)
    {
        //
    }

    /**
     * Don't dispatch an event.
     *
     * @param string|object $event
     * @param mixed $payload
     * @return mixed
     */
    public function until($event, $payload = [])
    {
        //
    }

    /**
     * Register an event listener with the dispatcher.
     */
    public function listen(
        string|array $events,
        string|array|QueuedCallable|null $listener = null
    ): void {
        $this->dispatcher->listen($events, $listener);
    }

    /**
     * Determine if a given event has listeners.
     *
     * @param string $eventName
     * @return bool
     */
    public function hasListeners($eventName)
    {
        return $this->dispatcher->hasListeners($eventName);
    }

    /**
     * Remove a set of listeners from the dispatcher.
     *
     * @param string $event
     * @return void
     */
    public function forget($event)
    {
        $this->dispatcher->forget($event);
    }

    /**
     * Dynamically pass method calls to the underlying dispatcher.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->forwardDecoratedCallTo($this->dispatcher, $method, $parameters);
    }
}
