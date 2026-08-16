<?php

namespace MacropaySolutions\Kernel\Database\Obvious\Concerns;

use InvalidArgumentException;
use MacropaySolutions\Kernel\Container\Container;
use MacropaySolutions\Kernel\Contracts\Events\Dispatcher;
use MacropaySolutions\Kernel\Events\NullDispatcher;
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
     *
     * @var array
     */
    protected $dispatchesEvents = [];

    /**
     * User exposed observable events.
     *
     * These are extra user-defined events observers may subscribe to.
     *
     * @var array
     */
    protected $observables = [];

    /**
     * Register observers with the model.
     *
     * @param object|array|string $classes
     * @return void
     *
     * @throws \RuntimeException
     */
    public static function observe($classes)
    {
        if (
            Container::getCachedFileContentsFromMemory(Container::OBSERVERS_PHP) !== null
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
     * @param object|string $class
     * @return void
     *
     * @throws \RuntimeException
     */
    protected function registerObserver($class)
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
     * @param object|string $class
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    private function resolveObserverClassName($class)
    {
        if (is_object($class)) {
            return get_class($class);
        }

        if (class_exists($class)) {
            return $class;
        }

        throw new InvalidArgumentException('Unable to find observer: ' . $class);
    }

    /**
     * Get the observable event names.
     *
     * @return array
     */
    public function getObservableEvents()
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
     *
     * @param array $observables
     * @return $this
     */
    public function setObservableEvents(array $observables)
    {
        $this->observables = $observables;

        return $this;
    }

    /**
     * Add an observable event name.
     *
     * @param array|mixed $observables
     * @return void
     */
    public function addObservableEvents($observables)
    {
        $this->observables = array_unique(
            array_merge(
                $this->observables,
                is_array($observables) ? $observables : func_get_args()
            )
        );
    }

    /**
     * Remove an observable event name.
     *
     * @param array|mixed $observables
     * @return void
     */
    public function removeObservableEvents($observables)
    {
        $this->observables = array_diff(
            $this->observables,
            is_array($observables) ? $observables : func_get_args()
        );
    }

    /**
     * Register a model event with the dispatcher.
     *
     * @param string $event
     * @param \MacropaySolutions\Kernel\Events\QueuedCallable|string|array $callback
     * @return void
     */
    protected static function registerModelEvent($event, $callback)
    {
        static::getEventDispatcher()->listen('obvious.' . $event . ': ' . static::class, $callback);
    }

    /**
     * Fire the given event for the model.
     *
     * @param string $event
     * @param bool $halt
     * @return mixed
     */
    protected function fireModelEvent($event, $halt = true)
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
     *
     * @param string $event
     * @param string $method
     * @return mixed|null
     */
    protected function fireCustomModelEvent($event, $method)
    {
        if (!isset($this->dispatchesEvents[$event])) {
            return;
        }

        $result = static::getEventDispatcher()->$method(new $this->dispatchesEvents[$event]($this));

        if (!is_null($result)) {
            return $result;
        }
    }

    /**
     * Filter the model event results.
     *
     * @param mixed $result
     * @return mixed
     */
    protected function filterModelEventResults($result)
    {
        if (is_array($result)) {
            $result = array_filter($result, function ($response) {
                return !is_null($response);
            });
        }

        return $result;
    }

    /**
     * Register a retrieved model event with the dispatcher.
     *
     * @param \MacropaySolutions\Kernel\Events\QueuedCallable|string|array $callback
     * @return void
     */
    public static function retrieved($callback)
    {
        static::registerModelEvent('retrieved', $callback);
    }

    /**
     * Register a saving model event with the dispatcher.
     *
     * @param \MacropaySolutions\Kernel\Events\QueuedCallable|string|array $callback
     * @return void
     */
    public static function saving($callback)
    {
        static::registerModelEvent('saving', $callback);
    }

    /**
     * Register a saved model event with the dispatcher.
     *
     * @param \MacropaySolutions\Kernel\Events\QueuedCallable|string|array $callback
     * @return void
     */
    public static function saved($callback)
    {
        static::registerModelEvent('saved', $callback);
    }

    /**
     * Register an updating model event with the dispatcher.
     *
     * @param \MacropaySolutions\Kernel\Events\QueuedCallable|string|array $callback
     * @return void
     */
    public static function updating($callback)
    {
        static::registerModelEvent('updating', $callback);
    }

    /**
     * Register an updated model event with the dispatcher.
     *
     * @param \MacropaySolutions\Kernel\Events\QueuedCallable|string|array $callback
     * @return void
     */
    public static function updated($callback)
    {
        static::registerModelEvent('updated', $callback);
    }

    /**
     * Register a creating model event with the dispatcher.
     *
     * @param \MacropaySolutions\Kernel\Events\QueuedCallable|string|array $callback
     * @return void
     */
    public static function creating($callback)
    {
        static::registerModelEvent('creating', $callback);
    }

    /**
     * Register a created model event with the dispatcher.
     *
     * @param \MacropaySolutions\Kernel\Events\QueuedCallable|string|array $callback
     * @return void
     */
    public static function created($callback)
    {
        static::registerModelEvent('created', $callback);
    }

    /**
     * Register a replicating model event with the dispatcher.
     *
     * @param \MacropaySolutions\Kernel\Events\QueuedCallable|string|array $callback
     * @return void
     */
    public static function replicating($callback)
    {
        static::registerModelEvent('replicating', $callback);
    }

    /**
     * Register a deleting model event with the dispatcher.
     *
     * @param \MacropaySolutions\Kernel\Events\QueuedCallable|string|array $callback
     * @return void
     */
    public static function deleting($callback)
    {
        static::registerModelEvent('deleting', $callback);
    }

    /**
     * Register a deleted model event with the dispatcher.
     *
     * @param \MacropaySolutions\Kernel\Events\QueuedCallable|string|array $callback
     * @return void
     */
    public static function deleted($callback)
    {
        static::registerModelEvent('deleted', $callback);
    }

    /**
     * Remove all the event listeners for the model.
     *
     * @return void
     */
    public static function flushEventListeners()
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
     *
     * @return void
     */
    public static function unsetEventDispatcher()
    {
        self::$dispatcher = null;
    }

    /**
     * Execute a callback without firing any model events for any model type.
     *
     * @param callable $callback
     * @return mixed
     */
    public static function withoutEvents(callable $callback)
    {
        $dispatcher = static::getEventDispatcher();

        if ($dispatcher) {
            static::setEventDispatcher(new NullDispatcher($dispatcher));
        }

        try {
            return $callback();
        } finally {
            if ($dispatcher) {
                static::setEventDispatcher($dispatcher);
            }
        }
    }
}
