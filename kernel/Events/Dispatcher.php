<?php

namespace MacropaySolutions\Kernel\Events;

use Closure;
use Exception;
use MacropaySolutions\Kernel\Container\Container;
use MacropaySolutions\Kernel\Contracts\Broadcasting\Factory as BroadcastFactory;
use MacropaySolutions\Kernel\Contracts\Broadcasting\ShouldBroadcast;
use MacropaySolutions\Kernel\Contracts\Container\Container as ContainerContract;
use MacropaySolutions\Kernel\Contracts\Events\Dispatcher as DispatcherContract;
use MacropaySolutions\Kernel\Contracts\Events\ShouldDispatchAfterCommit;
use MacropaySolutions\Kernel\Contracts\Events\ShouldHandleEventsAfterCommit;
use MacropaySolutions\Kernel\Contracts\Queue\ShouldBeEncrypted;
use MacropaySolutions\Kernel\Contracts\Queue\ShouldQueue;
use MacropaySolutions\Kernel\Contracts\Queue\ShouldQueueAfterCommit;
use MacropaySolutions\Kernel\Support\Arr;
use MacropaySolutions\Kernel\Support\Str;
use MacropaySolutions\Kernel\Support\Traits\Macroable;
use ReflectionClass;

class Dispatcher implements DispatcherContract
{
    use Macroable;

    /**
     * The IoC container instance.
     *
     * @var \MacropaySolutions\Kernel\Contracts\Container\Container
     */
    protected $container;

    /**
     * The registered event listeners.
     *
     * @var array
     */
    protected $listeners = [];

    /**
     * The wildcard listeners.
     *
     * @var array
     */
    protected $wildcards = [];

    /**
     * The cached wildcard listeners.
     *
     * @var array
     */
    protected $wildcardsCache = [];

    /**
     * The queue resolver instance.
     *
     * @var callable
     */
    protected $queueResolver;

    /**
     * The database transaction manager resolver instance.
     *
     * @var callable
     */
    protected $transactionManagerResolver;

    /**
     * Create a new event dispatcher instance.
     *
     * @param \MacropaySolutions\Kernel\Contracts\Container\Container|null $container
     * @return void
     */
    public function __construct(?ContainerContract $container = null)
    {
        $this->container = $container ?: new Container();
    }

    /**
     * Register an event listener with the dispatcher.
     *  For cached observers call listen(["obvious.{$event}: {$modelFQN}" => ["{$observerFQN}@{$event}", ], ])
     *  For cached or not cached event listeners call listen(["{$eventFQN}" => ["{$listenerFQN}[@handle]", ], ])
     * @return void
     */
    public function listen(
        string|array $events,
        string|array|QueuedCallable|null $listener = null
    ) {
        if ($listener instanceof QueuedCallable) {
            $listener = $listener->resolve();
        }

        foreach ((array)$events as $key => $event) {
            if (!\is_string($event)) {
                if (
                    $listener === null
                    && \is_string($key)
                    && \is_array($event)
                    && \array_is_list($event)
                ) {
                    $this->listeners = \array_merge_recursive($this->listeners, $events);

                    return null;
                }

                continue;
            }

            if (\str_contains($event, '*')) {
                $this->setupWildcardListen($event, $listener);

                continue;
            }

            $this->listeners[$event][] = $listener;
        }
    }

    /**
     * Setup a wildcard listener callback.
     *
     * @param string $event
     * @param \Closure|string $listener
     * @return void
     */
    protected function setupWildcardListen($event, $listener)
    {
        $this->wildcards[$event][] = $listener;

        $this->wildcardsCache = [];
    }

    /**
     * Determine if a given event has listeners.
     *
     * @param string $eventName
     * @return bool
     */
    public function hasListeners($eventName)
    {
        return isset($this->listeners[$eventName]) ||
            isset($this->wildcards[$eventName]) ||
            $this->hasWildcardListeners($eventName);
    }

    /**
     * Determine if the given event has any wildcard listeners.
     *
     * @param string $eventName
     * @return bool
     */
    public function hasWildcardListeners($eventName)
    {
        foreach ($this->wildcards as $key => $listeners) {
            if (Str::is($key, $eventName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Register an event subscriber with the dispatcher.
     *
     * @param object|string $subscriber
     * @return void
     */
    public function subscribe($subscriber)
    {
        $subscriber = $this->resolveSubscriber($subscriber);

        $events = $subscriber->subscribe($this);

        if (is_array($events)) {
            foreach ($events as $event => $listeners) {
                foreach (Arr::wrap($listeners) as $listener) {
                    if (is_string($listener) && method_exists($subscriber, $listener)) {
                        $this->listen($event, [get_class($subscriber), $listener]);

                        continue;
                    }

                    $this->listen($event, $listener);
                }
            }
        }
    }

    /**
     * Resolve the subscriber instance.
     *
     * @param object|string $subscriber
     * @return mixed
     */
    protected function resolveSubscriber($subscriber)
    {
        if (is_string($subscriber)) {
            return $this->container->make($subscriber);
        }

        return $subscriber;
    }

    /**
     * Fire an event until the first non-null response is returned.
     *
     * @param string|object $event
     * @param mixed $payload
     * @return mixed
     */
    public function until($event, $payload = [])
    {
        return $this->dispatch($event, $payload, true);
    }

    /**
     * Fire an event and call the listeners.
     *
     * @param string|object $event
     * @param mixed $payload
     * @param bool $halt
     * @return array|null
     */
    public function dispatch($event, $payload = [], $halt = false)
    {
        // When the given "event" is actually an object we will assume it is an event
        // object and use the class as the event name and this event itself as the
        // payload to the handler, which makes object based events quite simple.
        $isEventObject = \is_object($event);

        [$event, $payload] = $this->parseEventAndPayload($event, $payload);

        // If the event is not intended to be dispatched unless the current database
        // transaction is successful, we'll register a callback which will handle
        // dispatching this event on the next successful DB transaction commit.
        if (
            $isEventObject &&
            $payload[0] instanceof ShouldDispatchAfterCommit &&
            !is_null($transactions = $this->resolveTransactionManager())
        ) {
            $transactions->addCallback(
                fn() => $this->invokeListeners($event, $payload, $halt)
            );

            return null;
        }

        return $this->invokeListeners($event, $payload, $halt);
    }

    /**
     * Broadcast an event and call its listeners.
     *
     * @param string|object $event
     * @param mixed $payload
     * @param bool $halt
     * @return array|null
     */
    protected function invokeListeners($event, $payload, $halt = false)
    {
        if ($this->shouldBroadcast($payload)) {
            $this->broadcastEvent($payload[0]);
        }

        $responses = [];

        foreach ($this->getListeners($event) as $listener) {
            $response = $listener($event, $payload);

            // If a response is returned from the listener and event halting is enabled
            // we will just return this response, and not call the rest of the event
            // listeners. Otherwise we will add the response on the response list.
            if ($halt && !is_null($response)) {
                return $response;
            }

            // If a boolean false is returned from a listener, we will stop propagating
            // the event to any further listeners down in the chain, else we keep on
            // looping through the listeners and firing every one in our sequence.
            if ($response === false) {
                break;
            }

            $responses[] = $response;
        }

        return $halt ? null : $responses;
    }

    /**
     * Parse the given event and payload and prepare them for dispatching.
     *
     * @param mixed $event
     * @param mixed $payload
     * @return array
     */
    protected function parseEventAndPayload($event, $payload)
    {
        if (is_object($event)) {
            [$payload, $event] = [[$event], get_class($event)];

            return [$event, Arr::wrap($payload)];
        }

        if (\is_string($event) && \class_exists($event)) {
            $payload = Arr::wrap($payload);

            if (!isset($payload[0]) || !$payload[0] instanceof $event) {
                $params = (isset($payload[0]) && \is_array($payload[0])) ? $payload[0] : $payload;
                $payload = [\app($event, $params)];
            }
        }

        return [$event, Arr::wrap($payload)];
    }

    /**
     * Determine if the payload has a broadcastable event.
     *
     * @param array $payload
     * @return bool
     */
    protected function shouldBroadcast(array $payload)
    {
        return isset($payload[0]) &&
            $payload[0] instanceof ShouldBroadcast &&
            $this->broadcastWhen($payload[0]);
    }

    /**
     * Check if the event should be broadcasted by the condition.
     *
     * @param mixed $event
     * @return bool
     */
    protected function broadcastWhen($event)
    {
        return method_exists($event, 'broadcastWhen')
            ? $event->broadcastWhen() : true;
    }

    /**
     * Broadcast the given event class.
     *
     * @param \MacropaySolutions\Kernel\Contracts\Broadcasting\ShouldBroadcast $event
     * @return void
     */
    protected function broadcastEvent($event)
    {
        $this->container->make(BroadcastFactory::class)->queue($event);
    }

    /**
     * Get all the listeners for a given event name.
     *
     * @param string $eventName
     * @return array
     */
    public function getListeners($eventName)
    {
        $listeners = array_merge(
            $this->prepareListeners($eventName),
            $this->wildcardsCache[$eventName] ?? $this->getWildcardListeners($eventName)
        );

        return class_exists($eventName, false)
            ? $this->addInterfaceListeners($eventName, $listeners)
            : $listeners;
    }

    /**
     * Get the wildcard listeners for the event.
     *
     * @param string $eventName
     * @return array
     */
    protected function getWildcardListeners($eventName)
    {
        $wildcards = [];

        foreach ($this->wildcards as $key => $listeners) {
            if (Str::is($key, $eventName)) {
                foreach ($listeners as $listener) {
                    $wildcards[] = $this->makeListener($listener, true);
                }
            }
        }

        return $this->wildcardsCache[$eventName] = $wildcards;
    }

    /**
     * Add the listeners for the event's interfaces to the given array.
     *
     * @param string $eventName
     * @param array $listeners
     * @return array
     */
    protected function addInterfaceListeners($eventName, array $listeners = [])
    {
        foreach (class_implements($eventName) as $interface) {
            if (isset($this->listeners[$interface])) {
                foreach ($this->prepareListeners($interface) as $names) {
                    $listeners = array_merge($listeners, (array)$names);
                }
            }
        }

        return $listeners;
    }

    /**
     * Prepare the listeners for a given event.
     *
     * @param string $eventName
     * @return \Closure[]
     */
    protected function prepareListeners(string $eventName)
    {
        $listeners = [];

        foreach ($this->listeners[$eventName] ?? [] as $listener) {
            $listeners[] = $this->makeListener($listener);
        }

        return $listeners;
    }

    /**
     * Register an event listener with the dispatcher.
     *
     * @param \Closure|string|array $listener
     * @param bool $wildcard
     * @return \Closure
     */
    public function makeListener($listener, $wildcard = false)
    {
        if (is_string($listener)) {
            return $this->createClassListener($listener, $wildcard);
        }

        if (is_array($listener) && isset($listener[0]) && is_string($listener[0])) {
            return $this->createClassListener($listener, $wildcard);
        }

        return function ($event, $payload) use ($listener, $wildcard) {
            if ($wildcard) {
                return $listener($event, $payload);
            }

            return $listener(...array_values($payload));
        };
    }

    /**
     * Create a class based listener using the IoC container.
     *
     * @param string|array $listener
     * @param bool $wildcard
     * @return \Closure
     */
    public function createClassListener($listener, $wildcard = false)
    {
        return function ($event, $payload) use ($listener, $wildcard) {
            if ($wildcard) {
                return \call_user_func(
                    $this->createClassCallable($listener, $event),
                    $event,
                    $payload
                );
            }

            $callable = $this->createClassCallable($listener, $event);

            return $callable(...array_values($payload));
        };
    }

    /**
     * Create the class based event callable.
     *
     * @param array|string $listener
     * @param string|null $eventClass
     * @return callable
     */
    protected function createClassCallable($listener, ?string $eventClass = null)
    {
        [$class, $method] = $callable = \is_array($listener)
            ? $listener
            : $this->parseClassCallable($listener);

        if (\is_callable($callable)) {
            return $callable;
        }

        if (!method_exists($class, $method)) {
            $method = '__invoke';
        }

        $listener = $this->container->make($class);

        /**
         * @see createQueuedHandlerCallable
         * @see handlerShouldBeQueued
         * is bypassed
         */
        if ($listener instanceof ShouldQueue) {
            return function () use ($listener, $method, $eventClass) {
                $arguments = array_map(function ($a) {
                    return is_object($a) ? clone $a : $a;
                }, func_get_args());

                if (
                    !\method_exists($listener, 'shouldQueue')
                    || (isset($arguments[0]) ? $listener->shouldQueue($arguments[0]) : $listener->shouldQueue())
                ) {
                    $this->queueConnectionJob($listener, $arguments, $this->propagateListenerOptions(
                        $listener,
                        new CallQueuedListener($listener::class, $method, $arguments, $eventClass)
                    ));
                }
            };
        }

        return !\in_array($method, [
            'creating',
            'updating',
            'saving',
            'restoring',
            'replicating',
            'deleting',
            'forceDeleting',
        ], true) && $this->handlerShouldBeDispatchedAfterDatabaseTransactions($listener)
            ? $this->createCallbackForListenerRunningAfterCommits($listener, $method)
            : [$listener, $method];
    }

    /**
     * Parse the class listener into class and method.
     *
     * @param string $listener
     * @return array
     */
    protected function parseClassCallable($listener)
    {
        return Str::parseCallback($listener, 'handle');
    }

    /**
     * Determine if the event handler class should be queued.
     *
     * @param string $class
     * @return bool
     */
    protected function handlerShouldBeQueued($class)
    {
        return \is_subclass_of($class, ShouldQueue::class);
    }

    /**
     * Create a callable for putting an event handler on the queue.
     *
     * @param string $class
     * @param string $method
     * @return \Closure
     */
    protected function createQueuedHandlerCallable($class, $method, ?string $eventClass = null)
    {
        return function () use ($class, $method, $eventClass) {
            $arguments = array_map(function ($a) {
                return is_object($a) ? clone $a : $a;
            }, func_get_args());

            if ($this->handlerWantsToBeQueued($class, $arguments)) {
                $this->queueHandler($class, $method, $arguments, $eventClass);
            }
        };
    }

    /**
     * Determine if the given event handler should be dispatched after all database transactions have committed.
     *
     * @param object|mixed $listener
     * @return bool
     */
    protected function handlerShouldBeDispatchedAfterDatabaseTransactions($listener)
    {
        return (($listener->afterCommit ?? null) ||
                $listener instanceof ShouldHandleEventsAfterCommit) &&
            $this->resolveTransactionManager();
    }

    /**
     * Create a callable for dispatching a listener after database transactions.
     *
     * @param mixed $listener
     * @param string $method
     * @return \Closure
     */
    protected function createCallbackForListenerRunningAfterCommits($listener, $method)
    {
        return function () use ($method, $listener) {
            $payload = func_get_args();

            $this->resolveTransactionManager()->addCallback(
                function () use ($listener, $method, $payload) {
                    $listener->$method(...$payload);
                }
            );
        };
    }

    /**
     * Determine if the event handler wants to be queued.
     *
     * @param string $class
     * @param array $arguments
     * @return bool
     */
    protected function handlerWantsToBeQueued($class, $arguments)
    {
        $instance = $this->container->make($class);

        if (method_exists($instance, 'shouldQueue')) {
            return (isset($arguments[0]) ? $instance->shouldQueue($arguments[0]) : $instance->shouldQueue());
        }

        return true;
    }

    /**
     * Queue the handler class.
     *
     * @param string $class
     * @param string $method
     * @param array $arguments
     * @param string|null $eventClass
     * @return void
     */
    protected function queueHandler($class, $method, $arguments, ?string $eventClass = null)
    {
        [$listener, $job] = $this->createListenerAndJob($class, $method, $arguments, $eventClass);

        $this->queueConnectionJob($listener, $arguments, $job);
    }

    protected function queueConnectionJob(mixed $listener, array $arguments, mixed $job): void
    {
        $connection = $this->resolveQueue()->connection(
            method_exists($listener, 'viaConnection')
                ? (isset($arguments[0]) ? $listener->viaConnection($arguments[0]) : $listener->viaConnection())
                : $listener->connection ?? null
        );

        $queue = method_exists($listener, 'viaQueue')
            ? (isset($arguments[0]) ? $listener->viaQueue($arguments[0]) : $listener->viaQueue())
            : $listener->queue ?? null;

        $delay = method_exists($listener, 'withDelay')
            ? (isset($arguments[0]) ? $listener->withDelay($arguments[0]) : $listener->withDelay())
            : $listener->delay ?? null;

        is_null($delay)
            ? $connection->pushOn($queue, $job)
            : $connection->laterOn($queue, $delay, $job);
    }

    /**
     * Create the listener and job for a queued listener.
     *
     * @param string $class
     * @param string $method
     * @param array $arguments
     * @param string|null $eventClass
     * @return array
     */
    protected function createListenerAndJob($class, $method, $arguments, ?string $eventClass = null)
    {
        $reflector = new ReflectionClass($class);
        $constructor = $reflector->getConstructor();

        $listener = (
            !$constructor instanceof \ReflectionMethod
            || $constructor->getNumberOfParameters() === 0
        ) ? new $class() : $reflector->newInstanceWithoutConstructor();

        return [
            $listener,
            $this->propagateListenerOptions(
                $listener,
                new CallQueuedListener($class, $method, $arguments, $eventClass)
            ),
        ];
    }

    /**
     * Propagate listener options to the job.
     *
     * @param mixed $listener
     * @param \MacropaySolutions\Kernel\Events\CallQueuedListener $job
     * @return mixed
     */
    protected function propagateListenerOptions($listener, $job)
    {
        return tap($job, function ($job) use ($listener) {
            $event = $job->reconstructEvent();
            $data = \is_array($event) ? \array_values($event) : [$event];

            $job->afterCommit = ($listener instanceof ShouldQueueAfterCommit) ? true : ($listener->afterCommit ?? null);

            $job->backoff = method_exists($listener, 'backoff') ? $listener->backoff(
                ...$data
            ) : ($listener->backoff ?? null);
            $job->maxExceptions = $listener->maxExceptions ?? null;
            $job->retryUntil = method_exists($listener, 'retryUntil') ? $listener->retryUntil(...$data) : null;
            $job->shouldBeEncrypted = $listener instanceof ShouldBeEncrypted;
            $job->timeout = $listener->timeout ?? null;
            $job->failOnTimeout = $listener->failOnTimeout ?? false;
            $job->tries = $listener->tries ?? null;
        });
    }

    /**
     * Remove a set of listeners from the dispatcher.
     *
     * @param string $event
     * @return void
     */
    public function forget($event)
    {
        if (str_contains($event, '*')) {
            unset($this->wildcards[$event]);
        } else {
            unset($this->listeners[$event]);
        }

        foreach ($this->wildcardsCache as $key => $listeners) {
            if (Str::is($event, $key)) {
                unset($this->wildcardsCache[$key]);
            }
        }
    }

    /**
     * Get the queue implementation from the resolver.
     *
     * @return \MacropaySolutions\Kernel\Contracts\Queue\Queue
     */
    protected function resolveQueue()
    {
        return call_user_func($this->queueResolver);
    }

    /**
     * Set the queue resolver implementation.
     *
     * @param callable $resolver
     * @return $this
     */
    public function setQueueResolver(callable $resolver)
    {
        $this->queueResolver = $resolver;

        return $this;
    }

    /**
     * Get the database transaction manager implementation from the resolver.
     *
     * @return \MacropaySolutions\Kernel\Database\DatabaseTransactionsManager|null
     */
    protected function resolveTransactionManager()
    {
        return call_user_func($this->transactionManagerResolver);
    }

    /**
     * Set the database transaction manager resolver implementation.
     *
     * @param callable $resolver
     * @return $this
     */
    public function setTransactionManagerResolver(callable $resolver)
    {
        $this->transactionManagerResolver = $resolver;

        return $this;
    }

    /**
     * Gets the raw, unprepared listeners.
     *
     * @return array
     */
    public function getRawListeners()
    {
        return $this->listeners;
    }
}
