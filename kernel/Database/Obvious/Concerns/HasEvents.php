<?php

namespace MacropaySolutions\Kernel\Database\Obvious\Concerns;

use InvalidArgumentException;
use MacropaySolutions\Kernel\Container\Container;
use MacropaySolutions\Kernel\Contracts\Events\Dispatcher;
use MacropaySolutions\Kernel\Events\NullDispatcher;
use MacropaySolutions\Kernel\Events\QueuedCallable;
use MacropaySolutions\Kernel\Support\Arr;

trait HasEvents
{
    /**
     * The event dispatcher instance.
     */
    private static ?Dispatcher $dispatcher = null;

    /**
     * The event map for the model.
     *
     * Allows for object-based events for native Obvious events.
     */
    protected array $dispatchesEvents = [];

    /**
     * User exposed observable events.
     *
     * These are extra user-defined events observers may subscribe to.
     */
    protected array $observables = [];

    /**
     * Register observers with the model.
     *
     * @throws \RuntimeException
     */
    public static function observe(object|array|string $classes): void
    {
        if (
            Container::getInstance()->eventsAsObserversAreCached()
            && !Container::getInstance()->isBooted()
        ) {
            return;
        }

        $instance = new static();

        foreach (Arr::wrap($classes) as $class) {
            $instance->registerObserver($class);
        }
    }

    /**
     * Register a single observer with the model.
     *
     * @throws \RuntimeException
     */
    protected function registerObserver(object|string $class): void
    {
        $className = $this->resolveObserverClassName($class);

        // When registering a model observer, we will spin through the possible events
        // and determine if this observer has that method. If it does, we will hook
        // it into the model's event system, making it convenient to watch these.
        foreach (\array_intersect($this->getObservableEvents(), \get_class_methods($class) ?? []) as $event) {
            static::registerModelEvent($event, $className . '@' . $event);
        }
    }

    /**
     * Resolve the observer's class name from an object or string.
     *
     * @throws \InvalidArgumentException
     */
    private function resolveObserverClassName(object|string $class): string
    {
        if (is_object($class)) {
            return $class::class;
        }

        if (\class_exists($class)) {
            return $class;
        }

        throw new InvalidArgumentException('Unable to find observer: ' . $class);
    }

    /**
     * Get the observable event names.
     */
    public function getObservableEvents(): array
    {
        return array_merge(
            [
                'retrieved',
                'creating',
                'created',
                'updating',
                'updated',
                'saving',
                'saved',
                'restoring',
                'restored',
                'replicating',
                'deleting',
                'deleted',
                'forceDeleting',
                'forceDeleted',
            ],
            $this->observables
        );
    }

    /**
     * Set the observable event names.
     */
    public function setObservableEvents(array $observables): static
    {
        $this->observables = $observables;

        return $this;
    }

    /**
     * Add an observable event name.
     *
     * @param array|mixed $observables
     */
    public function addObservableEvents(mixed $observables): void
    {
        $this->observables = \array_unique(
            \array_merge(
                $this->observables,
                \is_array($observables) ? $observables : \func_get_args()
            )
        );
    }

    /**
     * Remove an observable event name.
     *
     * @param array|mixed $observables
     */
    public function removeObservableEvents(mixed $observables): void
    {
        $this->observables = \array_diff(
            $this->observables,
            \is_array($observables) ? $observables : \func_get_args()
        );
    }

    /**
     * Register a model event with the dispatcher.
     */
    protected static function registerModelEvent(string $event, QueuedCallable|string|array $callback): void
    {
        static::getEventDispatcher()->listen('obvious.' . $event . ': ' . static::class, $callback);
    }

    /**
     * Fire the given event for the model.
     */
    protected function fireModelEvent(string $event, bool $halt = true): mixed
    {
        // First, we will get the proper method to call on the event dispatcher, and then we
        // will attempt to fire a custom, object based event for the given event. If that
        // returns a result we can return that result, or we'll call the string events.
        $method = $halt ? 'until' : 'dispatch';

        $result = $this->filterModelEventResults(
            $this->fireCustomModelEvent($event, $method)
        );

        if ($result === false) {
            return false;
        }

        return !empty($result) ? $result : static::getEventDispatcher()->{$method}(
            'obvious.' . $event . ': ' . static::class,
            $this
        );
    }

    /**
     * Fire a custom model event for the given event.
     * @return mixed|null
     */
    protected function fireCustomModelEvent(string $event, string $method): mixed
    {
        if (!isset($this->dispatchesEvents[$event])) {
            return null;
        }

        $result = static::getEventDispatcher()->$method(new $this->dispatchesEvents[$event]($this));

        if (!is_null($result)) {
            return $result;
        }

        return null;
    }

    /**
     * Filter the model event results.
     */
    protected function filterModelEventResults(mixed $result): mixed
    {
        if (\is_array($result)) {
            $result = \array_filter($result, function ($response) {
                return null !== $response;
            });
        }

        return $result;
    }

    /**
     * Register a retrieved model event with the dispatcher.
     */
    public static function retrieved(QueuedCallable|string|array $callback): void
    {
        static::registerModelEvent('retrieved', $callback);
    }

    /**
     * Register a saving model event with the dispatcher.
     */
    public static function saving(QueuedCallable|string|array $callback): void
    {
        static::registerModelEvent('saving', $callback);
    }

    /**
     * Register a saved model event with the dispatcher.
     */
    public static function saved(QueuedCallable|string|array $callback): void
    {
        static::registerModelEvent('saved', $callback);
    }

    /**
     * Register an updating model event with the dispatcher.
     */
    public static function updating(QueuedCallable|string|array $callback): void
    {
        static::registerModelEvent('updating', $callback);
    }

    /**
     * Register an updated model event with the dispatcher.
     */
    public static function updated(QueuedCallable|string|array $callback): void
    {
        static::registerModelEvent('updated', $callback);
    }

    /**
     * Register a creating model event with the dispatcher.
     */
    public static function creating(QueuedCallable|string|array $callback): void
    {
        static::registerModelEvent('creating', $callback);
    }

    /**
     * Register a created model event with the dispatcher.
     */
    public static function created(QueuedCallable|string|array $callback): void
    {
        static::registerModelEvent('created', $callback);
    }

    /**
     * Register a replicating model event with the dispatcher.
     */
    public static function replicating(QueuedCallable|string|array $callback): void
    {
        static::registerModelEvent('replicating', $callback);
    }

    /**
     * Register a deleting model event with the dispatcher.
     */
    public static function deleting(QueuedCallable|string|array $callback): void
    {
        static::registerModelEvent('deleting', $callback);
    }

    /**
     * Register a deleted model event with the dispatcher.
     */
    public static function deleted(QueuedCallable|string|array $callback): void
    {
        static::registerModelEvent('deleted', $callback);
    }

    /**
     * Remove all the event listeners for the model.
     */
    public static function flushEventListeners(): void
    {
        if (!isset(self::$dispatcher)) {
            return;
        }

        $instance = new static();

        foreach ($instance->getObservableEvents() as $event) {
            self::$dispatcher->forget('obvious.' . $event . ': ' . static::class);
        }

        foreach ($instance->dispatchesEvents as $event) {
            self::$dispatcher->forget($event);
        }
    }

    /**
     * Get the event dispatcher instance.
     */
    public static function getEventDispatcher(): Dispatcher
    {
        return self::$dispatcher ??= Container::getInstance()->make('events');
    }

    /**
     * Set the event dispatcher instance.
     */
    public static function setEventDispatcher(Dispatcher $dispatcher): void
    {
        self::$dispatcher = $dispatcher;
    }

    /**
     * Unset the event dispatcher for models.
     */
    public static function unsetEventDispatcher(): void
    {
        self::$dispatcher = null;
    }

    /**
     * Execute a callback without firing any model events for any model type.
     */
    public static function withoutEvents(callable $callback): mixed
    {
        $dispatcher = static::getEventDispatcher();

        static::setEventDispatcher(new NullDispatcher($dispatcher));

        try {
            return $callback();
        } finally {
            static::setEventDispatcher($dispatcher);
        }
    }
}
