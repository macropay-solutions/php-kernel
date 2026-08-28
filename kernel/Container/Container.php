<?php

namespace MacropaySolutions\Kernel\Container;

use ArrayAccess;
use Closure;
use Exception;
use LogicException;
use MacropaySolutions\Kernel\Contracts\Container\BindingResolutionException;
use MacropaySolutions\Kernel\Contracts\Container\CircularDependencyException;
use MacropaySolutions\Kernel\Contracts\Container\Container as ContainerContract;
use MacropaySolutions\Kernel\Contracts\Foundation\CachesConfiguration;
use MacropaySolutions\Kernel\Contracts\Foundation\CachesRoutes;
use ReflectionException;
use TypeError;

class Container implements ArrayAccess, ContainerContract, CachesConfiguration, CachesRoutes
{
    /**
     * The Kernel-Framework version.
     *
     * @var string
     */
    public const VERSION = '1.0.0';

    /**
     * Override this in your \App\Application with true if you need/want
     */
    public const DEFAULT_PARAMETER_TAKES_PRECEDENCE_WHEN_AUTOWIRING = false;
    public const MERGED_CACHED_FILES_PHP = 'merged-cached-files.php';
    public const SERVICES_PHP = 'services.php';
    public const CONFIG_PHP = 'config.php';
    public const ROUTES_PHP = 'routes-v8.php';
    public const AUTOWIRING_PHP = 'autowiring.php';
    public const RESOLVING_EVENTS_PHP = 'resolving-events.php';
    public const EVENTS_PHP = 'events.php';
    public const OBSERVERS_PHP = 'observers.php';
    public const COMMANDS_PHP = 'commands.php';
    public const TAGGED_CACHE_TTL_CAP_SECONDS = 7200;

    /**
     * The current globally available container (if any).
     *
     * @var static
     */
    protected static $instance;

    /**
     * Limit of extra memory used to detect circular dependencies when resolving an abstract from container (bytes)
     */
    protected static int $circularDependencyMemoryLimit = 0;


    /**
     * Merged cached files from bootstrap/cache
     */
    protected static ?array $bootstrapCachedFiles = null;

    /**
     * Map with memory in bytes used before resolving the abstract from container
     * Map with count for resolving the abstract from container
     */
    protected array $monitorResolvingAbstractMap = [];

    /**
     * An array of the types that have been resolved.
     *
     * @var bool[]
     */
    protected $resolved = [];

    /**
     * The container's bindings.
     *
     * @var array[]
     */
    protected $bindings = [];

    /**
     * The container's method bindings.
     *
     * @var \Closure[]
     */
    protected $methodBindings = [];

    /**
     * The container's shared instances.
     *
     * @var object[]
     */
    protected $instances = [];

    /**
     * The container's scoped instances.
     *
     * @var array
     */
    protected $scopedInstances = [];

    /**
     * The registered type aliases.
     *
     * @var string[]
     */
    protected $aliases = [];

    /**
     * The registered aliases keyed by the abstract name.
     *
     * @var array[]
     */
    protected $abstractAliases = [];

    /**
     * @var string[]
     */
    protected array $alreadyRetrievedAliases = [];

    /**
     * The extension closures for services.
     *
     * @var array[]
     */
    protected $extenders = [];

    /**
     * All the registered tags.
     *
     * @var array[]
     */
    protected $tags = [];

    /**
     * All the registered rebound callbacks.
     *
     * @var array[]
     */
    protected $reboundCallbacks = [];

    /**
     * All the global before resolving callbacks.
     *
     * @var \Closure[]
     */
    protected $globalBeforeResolvingCallbacks = [];

    /**
     * All the global resolving callbacks.
     *
     * @var \Closure[]
     */
    protected $globalResolvingCallbacks = [];

    /**
     * All the global after resolving callbacks.
     *
     * @var \Closure[]
     */
    protected $globalAfterResolvingCallbacks = [];

    /**
     * All the before resolving callbacks by class type.
     *
     * @var array[]
     */
    protected $beforeResolvingCallbacks = [];

    /**
     * All the resolving callbacks by class type.
     *
     * @var array[]
     */
    protected $resolvingCallbacks = [];

    /**
     * All the after resolving callbacks by class type.
     *
     * @var array[]
     */
    protected $afterResolvingCallbacks = [];

    protected ?string $cachedEnvironment = null;
    protected static bool $isDevEnv = false;

    public static function isDevEnv(): bool
    {
        return static::$isDevEnv;
    }

    public static function getAbstractToTypeOfResolvingCallbacksEventsAsKeys(): array
    {
        return static::getCachedFileContentsFromMemory(static::RESOLVING_EVENTS_PHP) ?? [];
    }

    /**
     * Get the version number of the application.
     */
    public function version(): string
    {
        return static::VERSION;
    }

    /**
     * Determine if the given abstract type has been bound.
     *
     * @param string $abstract
     * @return bool
     */
    public function bound($abstract)
    {
        return isset($this->bindings[$abstract]) ||
            isset($this->instances[$abstract]) ||
            isset($this->aliases[$abstract]);
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $id): bool
    {
        return $this->bound($id);
    }

    /**
     * Determine if the given abstract type has been resolved.
     *
     * @param string $abstract
     * @return bool
     */
    public function resolved($abstract)
    {
        $abstract = $this->getAlias($abstract);

        return isset($this->resolved[$abstract]) ||
            isset($this->instances[$abstract]);
    }

    /**
     * Determine if a given type is shared.
     *
     * @param string $abstract
     * @return bool
     */
    public function isShared($abstract)
    {
        return isset($this->instances[$abstract]) ||
            (isset($this->bindings[$abstract]['shared']) &&
                $this->bindings[$abstract]['shared'] === true);
    }

    /**
     * Determine if a given string is an alias.
     *
     * @param string $name
     * @return bool
     */
    public function isAlias($name)
    {
        return isset($this->aliases[$name]);
    }

    /**
     * Register a binding with the container.
     *
     * @param string $abstract
     * @param \Closure|string|null $concrete
     * @param bool $shared
     * @return void
     *
     * @throws \TypeError
     */
    public function bind($abstract, $concrete = null, $shared = false)
    {
        $this->dropStaleInstances($abstract);

        // If the factory is not a Closure, it means it is just a class name which is
        // bound into this container to the abstract type and we will just wrap it
        // up inside its own Closure to give us more convenience when extending.
        if (!$concrete instanceof Closure) {
            if (!\is_string($concrete ??= $abstract)) {
                throw new TypeError(
                    self::class . '::bind(): Argument #2 ($concrete) must be of type Closure|string|null'
                );
            }

            $concrete = $this->getClosure($abstract, $concrete);
        }

        $this->bindings[$abstract] = ['concrete' => $concrete, 'shared' => $shared];

        // If the abstract type was already resolved in this container we'll fire the
        // rebound listener so that any objects which have already gotten resolved
        // can have their copy of the object updated via the listener callbacks.
        if ($this->resolved($abstract)) {
            $this->rebound($abstract);
        }
    }

    /**
     * Set all the container bindings that should be registered when the app is instantiated
     * @see \MacropaySolutions\Kernel\Container\Container::getClosure for Closure format
     * Must set array shape:
     * [
     *     "{$abstractString}" => [
     *         'concrete' => \Closure,
     *         'shared' => bool
     *     ],
     * ]
     */
    protected function registerExplicitBindingsMap(): void
    {
//        $this->bindings = [
//            \MacropaySolutions\Kernel\Http\Request::class => [
//                'concrete' => static function (
//                     \MacropaySolutions\Kernel\Contracts\Container\Container $container,
//                     array $parameters = []
//                ): \MacropaySolutions\Kernel\Http\Request {
//                    return $container->resolve(
//                        \App\Requests\Request::class, // your child class
//                        $parameters,
//                        false
//                    );
//                },
//                'shared' => false
//            ],
//        ];
    }

    /**
     * Get the Closure to be used when building a type.
     *
     * @param string $abstract
     * @param string $concrete
     * @return \Closure
     */
    protected function getClosure($abstract, $concrete)
    {
        return static function ($container, $parameters = []) use ($abstract, $concrete) {
            if ($abstract == $concrete) {
                return $container->build($concrete);
            }

            return $container->resolve(
                $concrete,
                $parameters,
                false // raiseEvents
            );
        };
    }

    /**
     * Determine if the container has a method binding.
     *
     * @param string $method
     * @return bool
     */
    public function hasMethodBinding($method)
    {
        return isset($this->methodBindings[$method]);
    }

    /**
     * Bind a callback to resolve with Container::call.
     *
     * @param array|string $method
     * @param \Closure $callback
     * @return void
     */
    public function bindMethod($method, $callback)
    {
        $this->methodBindings[$this->parseBindMethod($method)] = $callback;
    }

    /**
     * Get the method to be bound in class@method format.
     *
     * @param array|string $method
     * @return string
     */
    protected function parseBindMethod($method)
    {
        if (is_array($method)) {
            return $method[0] . '@' . $method[1];
        }

        return $method;
    }

    /**
     * Get the method binding for the given method.
     *
     * @param string $method
     * @param mixed $instance
     * @return mixed
     */
    public function callMethodBinding($method, $instance)
    {
        return $this->methodBindings[$method]($instance, $this);
    }

    /**
     * Register a binding if it hasn't already been registered.
     *
     * @param string $abstract
     * @param \Closure|string|null $concrete
     * @param bool $shared
     * @return void
     */
    public function bindIf($abstract, $concrete = null, $shared = false)
    {
        if (!$this->bound($abstract)) {
            $this->bind($abstract, $concrete, $shared);
        }
    }

    /**
     * Register a shared binding in the container.
     *
     * @param string $abstract
     * @param \Closure|string|null $concrete
     * @return void
     */
    public function singleton($abstract, $concrete = null)
    {
        $this->bind($abstract, $concrete, true);
    }

    /**
     * Register a shared binding if it hasn't already been registered.
     *
     * @param string $abstract
     * @param \Closure|string|null $concrete
     * @return void
     */
    public function singletonIf($abstract, $concrete = null)
    {
        if (!$this->bound($abstract)) {
            $this->singleton($abstract, $concrete);
        }
    }

    /**
     * Register a scoped binding in the container.
     *
     * @param string $abstract
     * @param \Closure|string|null $concrete
     * @return void
     */
    public function scoped($abstract, $concrete = null)
    {
        $this->scopedInstances[] = $abstract;

        $this->singleton($abstract, $concrete);
    }

    /**
     * Register a scoped binding if it hasn't already been registered.
     *
     * @param string $abstract
     * @param \Closure|string|null $concrete
     * @return void
     */
    public function scopedIf($abstract, $concrete = null)
    {
        if (!$this->bound($abstract)) {
            $this->scoped($abstract, $concrete);
        }
    }

    /**
     * "Extend" an abstract type in the container.
     *
     * @param string $abstract
     * @param \Closure $closure
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    public function extend($abstract, Closure $closure)
    {
        $abstract = $this->getAlias($abstract);

        if (isset($this->instances[$abstract])) {
            $this->instances[$abstract] = $closure($this->instances[$abstract], $this);

            $this->rebound($abstract);
        } else {
            $this->extenders[$abstract][] = $closure;

            if ($this->resolved($abstract)) {
                $this->rebound($abstract);
            }
        }
    }

    /**
     * Register an existing instance as shared in the container.
     *
     * @param string $abstract
     * @param mixed $instance
     * @return mixed
     */
    public function instance($abstract, $instance)
    {
        $this->removeAbstractAlias($abstract);

        $isBound = $this->bound($abstract);

        unset($this->aliases[$abstract]);

        // We'll check to determine if this type has been bound before, and if it has
        // we will fire the rebound callbacks registered with the container and it
        // can be updated with consuming classes that have gotten resolved here.
        $this->instances[$abstract] = $instance;

        if ($isBound) {
            $this->rebound($abstract);
        }

        return $instance;
    }

    /**
     * @throws BindingResolutionException
     * @throws CircularDependencyException
     * @throws ReflectionException
     */
    public function isInInstances(string $abstract, mixed $instance, bool $resolve = true): bool
    {
        return $instance === ($this->instances[$abstract] ?? ($resolve ? $this->resolve($abstract) : null));
    }

    /**
     * Remove an alias from the contextual binding alias cache.
     *
     * @param string $searched
     * @return void
     */
    protected function removeAbstractAlias($searched)
    {
        $this->alreadyRetrievedAliases = [];

        if (!isset($this->aliases[$searched])) {
            return;
        }

        foreach ($this->abstractAliases as $abstract => $aliases) {
            if (false === $index = \array_search($searched, $aliases)) {
                continue;
            }

            if (\count($aliases) === 1) {
                unset($this->abstractAliases[$abstract]);

                continue;
            }

            unset($this->abstractAliases[$abstract][$index]);
        }
    }

    /**
     * Assign a set of tags to a given binding.
     *
     * @param array|string $abstracts
     * @param array|mixed ...$tags
     * @return void
     */
    public function tag($abstracts, $tags)
    {
        $tags = is_array($tags) ? $tags : array_slice(func_get_args(), 1);

        foreach ($tags as $tag) {
            if (!isset($this->tags[$tag])) {
                $this->tags[$tag] = [];
            }

            foreach ((array)$abstracts as $abstract) {
                $this->tags[$tag][] = $abstract;
            }
        }
    }

    /**
     * Resolve all the bindings for a given tag.
     *
     * @param string $tag
     * @return iterable
     */
    public function tagged($tag)
    {
        if (!isset($this->tags[$tag])) {
            return [];
        }

        return new RewindableGenerator(function () use ($tag) {
            foreach ($this->tags[$tag] as $abstract) {
                yield $this->make($abstract);
            }
        }, count($this->tags[$tag]));
    }

    /**
     * Alias a type to a different name.
     *
     * @param string $abstract
     * @param string $alias
     * @return void
     *
     * @throws \LogicException
     */
    public function alias($abstract, $alias)
    {
        if ($alias === $abstract) {
            throw new LogicException("[{$abstract}] is aliased to itself.");
        }

        $this->removeAbstractAlias($alias);

        $this->aliases[$alias] = $abstract;

        $this->abstractAliases[$abstract][] = $alias;
    }

    /**
     * Bind a new callback to an abstract's rebind event.
     *
     * @param string $abstract
     * @param \Closure $callback
     * @return mixed
     */
    public function rebinding($abstract, Closure $callback)
    {
        $this->reboundCallbacks[$abstract = $this->getAlias($abstract)][] = $callback;

        if ($this->bound($abstract)) {
            return $this->make($abstract);
        }
    }

    /**
     * Refresh an instance on the given target and method.
     *
     * @param string $abstract
     * @param mixed $target
     * @param string $method
     * @return mixed
     */
    public function refresh($abstract, $target, $method)
    {
        return $this->rebinding($abstract, static function ($app, $instance) use ($target, $method) {
            $target->{$method}($instance);
        });
    }

    /**
     * Fire the "rebound" callbacks for the given abstract type.
     *
     * @param string $abstract
     * @return void
     */
    protected function rebound($abstract)
    {
        $instance = $this->make($abstract);

        foreach ($this->getReboundCallbacks($abstract) as $callback) {
            $callback($this, $instance);
        }
    }

    /**
     * Get the rebound callbacks for a given type.
     *
     * @param string $abstract
     * @return array
     */
    protected function getReboundCallbacks($abstract)
    {
        return $this->reboundCallbacks[$abstract] ?? [];
    }

    /**
     * Wrap the given closure such that its dependencies will be injected when executed.
     *
     * @param \Closure $callback
     * @param array $parameters
     * @return \Closure
     */
    public function wrap(Closure $callback, array $parameters = [])
    {
        return fn() => $this->call($callback, $parameters);
    }

    /**
     * Call the given Closure / class@method and inject its dependencies.
     *
     * @param callable|string $callback
     * @param array<string, mixed> $parameters
     * @param string|null $defaultMethod
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    public function call($callback, array $parameters = [], $defaultMethod = null)
    {
        return BoundMethod::call($this, $callback, $parameters, $defaultMethod);
    }

    /**
     * Get a closure to resolve the given type from the container.
     *
     * @param string $abstract
     * @return \Closure
     */
    public function factory($abstract)
    {
        return fn() => $this->make($abstract);
    }

    /**
     * An alias function name for make().
     *
     * @param string|callable $abstract
     * @param array $parameters
     * @return mixed
     *
     * @throws \MacropaySolutions\Kernel\Contracts\Container\BindingResolutionException
     */
    public function makeWith($abstract, array $parameters = [])
    {
        return $this->make($abstract, $parameters);
    }

    /**
     * Resolve the given type from the container.
     *
     * @param string|callable $abstract
     * @param array $parameters
     * @return mixed
     *
     * @throws BindingResolutionException
     * @throws CircularDependencyException
     * @throws ReflectionException
     */
    public function make($abstract, array $parameters = [])
    {
        return $this->resolve($abstract, $parameters);
    }

    /**
     * Resolve the given type from the container.
     *
     * @throws BindingResolutionException
     * @throws CircularDependencyException
     * @throws ReflectionException
     */
    public function makeWithoutAlias(string $abstract, array $parameters = []): mixed
    {
        return $this->resolveWithoutAlias($abstract, $parameters);
    }

    /**
     * {@inheritdoc}
     *
     * @return mixed
     */
    public function get(string $id)
    {
        try {
            return $this->resolve($id);
        } catch (Exception $e) {
            if ($this->has($id) || $e instanceof CircularDependencyException) {
                throw $e;
            }

            throw new EntryNotFoundException($id, is_int($e->getCode()) ? $e->getCode() : 0, $e);
        }
    }

    /**
     * Resolve the given type from the container.
     *
     * @param string|callable $abstract
     * @param array $parameters
     * @param bool $raiseEvents
     * @return mixed
     *
     * @throws \MacropaySolutions\Kernel\Contracts\Container\BindingResolutionException
     * @throws \MacropaySolutions\Kernel\Contracts\Container\CircularDependencyException
     * @throws ReflectionException
     */
    protected function resolve($abstract, $parameters = [], $raiseEvents = true)
    {
        if (\is_string($abstract)) {
            return $this->resolveString($abstract, $parameters, $raiseEvents);
        }

        // We're ready to instantiate an instance of the concrete type registered for
        // the binding. This will instantiate the types, as well as resolve any of
        // its "nested" dependencies recursively until all have gotten resolved.
        $object = $this->build($abstract, $parameters);

        if ($raiseEvents) {
            $this->fireResolvingCallbacks($abstract, $object);
        }

        return $object;
    }

    /**
     * Resolve the given final string from the container without alias.
     *
     * @throws \MacropaySolutions\Kernel\Contracts\Container\BindingResolutionException
     * @throws \MacropaySolutions\Kernel\Contracts\Container\CircularDependencyException
     * @throws ReflectionException
     */
    protected function resolveWithoutAlias(string $abstract, array $parameters = [], $raiseEvents = true): mixed
    {
        if ($abstract === '') {
            throw new BindingResolutionException('Can\'t resolve empty string');
        }

        return $this->resolveFinalString($abstract, $parameters, $raiseEvents);
    }

    /**
     * @throws BindingResolutionException
     * @throws CircularDependencyException
     * @throws ReflectionException
     */
    protected function resolveString(string $abstract, array $parameters = [], bool $raiseEvents = true): mixed
    {
        if ($abstract === '') {
            throw new BindingResolutionException('Can\'t resolve empty string');
        }

        return $this->resolveFinalString($this->getAlias($abstract), $parameters, $raiseEvents);
    }

    /**
     * @throws BindingResolutionException
     * @throws CircularDependencyException
     * @throws ReflectionException
     */
    protected function resolveFinalString(string $abstract, array $parameters, bool $raiseEvents = true): mixed
    {
        // First we'll fire any event handlers which handle the "before" resolving of
        // specific types. This gives some hooks the chance to add various extends
        // calls to change the resolution of objects that they're interested in.
        if ($raiseEvents) {
            $this->fireBeforeResolvingCallbacks($abstract, $parameters);
        }

        $hasParameterOverrides = [] !== $parameters;

        // If an instance of the type is currently being managed as a singleton we'll
        // just return an existing instance instead of instantiating new instances
        // so the developer can keep using the same objects instance every time.
        if (isset($this->instances[$abstract]) && !$hasParameterOverrides) {
            return $this->instances[$abstract];
        }

        $concrete = $this->getConcrete($abstract);

        // We're ready to instantiate an instance of the concrete type registered for
        // the binding. This will instantiate the types, as well as resolve any of
        // its "nested" dependencies recursively until all have gotten resolved.
        $object = $this->handleObjectInstantiationLogic($concrete, $parameters, $abstract);

        // If we defined any extenders for this type, we'll need to spin through them
        // and apply them to the object being built. This allows for the extension
        // of services, such as changing configuration or decorating the object.
        if (isset($this->extenders[$abstract])) {
            foreach ($this->extenders[$abstract] as $extender) {
                $object = $extender($object, $this);
            }
        }

        // If the requested type is registered as a singleton we'll want to cache off
        // the instances in "memory" so we can return it later without creating an
        // entirely new instance of an object on each subsequent request for it.
        if ($this->isShared($abstract) && !$hasParameterOverrides) {
            $this->instances[$abstract] = $object;
        }

        if ($raiseEvents) {
            $this->fireResolvingCallbacks($abstract, $object);
        }

        // Before returning, we will also set the resolved flag to "true" and pop off
        // the parameter overrides for this build. After those two things are done
        // we will be ready to return back the fully constructed class instance.
        $this->resolved[$abstract] = true;

        return $object;
    }

    /**
     * @throws BindingResolutionException
     * @throws CircularDependencyException
     * @throws ReflectionException
     */
    protected function handleObjectInstantiationLogic(mixed $concrete, array $parameters, string $abstract): mixed
    {
        if (static::$circularDependencyMemoryLimit > 0) {
            return $this->getObject($concrete, $this->isBuildable($concrete, $abstract), $abstract, $parameters);
        }

        if (!$this->isBuildable($concrete, $abstract)) {
            return $this->make($concrete, $parameters);
        }

        return $this->build($concrete, $parameters);
    }

    /**
     * @throws BindingResolutionException
     * @throws CircularDependencyException
     * @throws ReflectionException
     */
    protected function getObject(
        mixed $concrete,
        bool $isBuildable,
        string $initialAbstract,
        array $parameters = []
    ): mixed {
        if (($this->monitorResolvingAbstractMap[$initialAbstract]['i'] ??= 0) < 7) {
            return $this->returnObject($concrete, $isBuildable, $initialAbstract, $parameters);
        }

        if (!isset($this->monitorResolvingAbstractMap[$initialAbstract]['b'])) {
            $this->monitorResolvingAbstractMap[$initialAbstract]['b'] = \memory_get_usage(true);

            return $this->returnObject($concrete, $isBuildable, $initialAbstract, $parameters);
        }

        if (
            static::$circularDependencyMemoryLimit >
                (\memory_get_usage(true) - $this->monitorResolvingAbstractMap[$initialAbstract]['b'])
        ) {
            return $this->returnObject($concrete, $isBuildable, $initialAbstract, $parameters);
        }

        throw new CircularDependencyException(
            'Circular dependency detected while resolving [' . $initialAbstract .
                ']. Memory limit difference reached or exceeded ' . static::$circularDependencyMemoryLimit .
                ' bytes: ' . \json_encode($this->monitorResolvingAbstractMap)
        );
    }

    /**
     * @throws BindingResolutionException
     * @throws CircularDependencyException
     * @throws ReflectionException
     */
    protected function returnObject(
        mixed $concrete,
        bool $isBuildable,
        string $initialAbstract,
        array $parameters = []
    ): mixed {
        try {
            $this->monitorResolvingAbstractMap[$initialAbstract]['i']++;

            return $isBuildable ? $this->build($concrete, $parameters) : $this->make($concrete, $parameters);
        } finally {
            if (0 === --$this->monitorResolvingAbstractMap[$initialAbstract]['i']) {
                unset($this->monitorResolvingAbstractMap[$initialAbstract]);
            }
        }
    }

    /**
     * Get the concrete type for a given abstract.
     *
     * @param string $abstract
     * @return mixed
     */
    protected function getConcrete($abstract)
    {
        // If we don't have a registered resolver or concrete for the type, we'll just
        // assume each type is a concrete name and will attempt to resolve it as is
        // since the container should be able to resolve concretes automatically.
        if (isset($this->bindings[$abstract])) {
            return $this->bindings[$abstract]['concrete'];
        }

        return $abstract;
    }

    /**
     * Determine if the given concrete is buildable.
     *
     * @param mixed $concrete
     * @param string $abstract
     * @return bool
     */
    protected function isBuildable($concrete, $abstract)
    {
        return $concrete === $abstract || $concrete instanceof Closure;
    }

    /**
     * Instantiate a concrete instance of the given type.
     *
     * @throws \MacropaySolutions\Kernel\Contracts\Container\BindingResolutionException
     * @throws \MacropaySolutions\Kernel\Contracts\Container\CircularDependencyException
     * @throws ReflectionException
     */
    public function build(\Closure|string $concrete, array $parameters = []): mixed
    {
        if ($concrete instanceof Closure) {
            return $concrete($this, $parameters);
        }

        if (!\class_exists($concrete)) {
            if (\interface_exists($concrete)) {
                throw new BindingResolutionException('Target interface [' . $concrete . '] is not instantiable.');
            }

            if (\trait_exists($concrete)) {
                throw new BindingResolutionException('Target trait [' . $concrete . '] is not instantiable.');
            }

            throw new BindingResolutionException('Target class [' . $concrete . '] does not exist.');
        }

        BoundMethod::addToClassesFqnsToCacheForAutowire($concrete);

        if (
            [] === BoundMethod::getAndCachePrecompiledAutoWiringClassMethodParametersMapForClassAndMethod(
                \ltrim($concrete, '\\'),
                '__construct'
            )
        ) {
            return new $concrete();
        }

        if ($parameters !== [] && \array_is_list($parameters)) {
            return new $concrete(...$parameters);
        }

        return new $concrete(...\array_values(BoundMethod::getConstructDependencies(
            $this,
            $concrete,
            $parameters
        )));
    }

    /**
     * Register a new before resolving callback for all types.
     *
     * @param \Closure|string $abstract
     * @param \Closure|null $callback
     * @return void
     */
    public function beforeResolving($abstract, ?Closure $callback = null)
    {
        $abstract = $this->getAlias($abstract);

        if ($abstract instanceof Closure && null === $callback) {
            $this->globalBeforeResolvingCallbacks[] = $abstract;
        } else {
            $this->beforeResolvingCallbacks[$abstract][] = $callback;
        }
    }

    /**
     * Register a new resolving callback.
     *
     * @param \Closure|string $abstract
     * @param \Closure|null $callback
     * @return void
     *
     * Note that the execution speed decreases with the increase of resolvingCallbacks !!!
     */
    public function resolving($abstract, ?Closure $callback = null)
    {
        $abstract = $this->getAlias($abstract);

        if (null === $callback && $abstract instanceof Closure) {
            $this->globalResolvingCallbacks[] = $abstract;
        } else {
            $this->resolvingCallbacks[$abstract][] = $callback;
        }
    }

    /**
     * Register a new after resolving callback for all types.
     *
     * @param \Closure|string $abstract
     * @param \Closure|null $callback
     * @return void
     *
     * Note that the execution speed decreases with the increase of resolvingCallbacks !!!
     */
    public function afterResolving($abstract, ?Closure $callback = null)
    {
        $abstract = $this->getAlias($abstract);

        if ($abstract instanceof Closure && null === $callback) {
            $this->globalAfterResolvingCallbacks[] = $abstract;
        } else {
            $this->afterResolvingCallbacks[$abstract][] = $callback;
        }
    }

    /**
     * Fire all the before resolving callbacks.
     *
     * @param string $abstract
     * @param array $parameters
     * @return void
     */
    protected function fireBeforeResolvingCallbacks($abstract, $parameters = [])
    {
        if ([] !== $this->globalBeforeResolvingCallbacks) {
            $this->fireBeforeCallbackArray($abstract, $parameters, $this->globalBeforeResolvingCallbacks);
        }

        if ([] !== $this->beforeResolvingCallbacks) {
            $this->fireBeforeCallbackArray(
                $abstract,
                $parameters,
                $this->getBeforeResolvingCallbacksForType($abstract)
            );
        }
    }

    /**
     * Fire an array of callbacks with an object.
     *
     * @param string $abstract
     * @param array $parameters
     * @param array $callbacks
     * @return void
     */
    protected function fireBeforeCallbackArray($abstract, $parameters, array $callbacks)
    {
        foreach ($callbacks as $callback) {
            $callback($abstract, $parameters, $this);
        }
    }

    /**
     * Fire all the resolving callbacks.
     *
     * @param string|Closure $abstract
     * @param mixed $object
     * @return void
     */
    protected function fireResolvingCallbacks($abstract, $object)
    {
        $this->fireCallbackArray($object, $this->globalResolvingCallbacks);

        $this->fireCallbackArray(
            $object,
            $this->getResolvingCallbacksForType($abstract, $object)
        );

        $this->fireAfterResolvingCallbacks($abstract, $object);
    }

    /**
     * Fire all the after resolving callbacks.
     *
     * @param string|Closure $abstract
     * @param mixed $object
     * @return void
     */
    protected function fireAfterResolvingCallbacks($abstract, $object)
    {
        $this->fireCallbackArray($object, $this->globalAfterResolvingCallbacks);

        $this->fireCallbackArray(
            $object,
            $this->getAfterResolvingCallbacksForType($abstract, $object)
        );
    }

    /**
     * Get all before resolving callbacks for a given type.
     */
    protected function getBeforeResolvingCallbacksForType(mixed $abstract): array
    {
        if ([] === $this->beforeResolvingCallbacks || !\is_string($abstract)) {
            return [];
        }

        if (
            isset(
                static::getCachedFileContentsFromMemory(static::RESOLVING_EVENTS_PHP)['beforeResolving'][$abstract]
            )
        ) {
            if ([] === static::$bootstrapCachedFiles[static::RESOLVING_EVENTS_PHP]['beforeResolving'][$abstract]) {
                return [];
            }

            $groups = \array_intersect_key(
                $this->beforeResolvingCallbacks,
                static::$bootstrapCachedFiles[static::RESOLVING_EVENTS_PHP]['beforeResolving'][$abstract]
            );

            return $groups !== [] ? \array_merge(...\array_values($groups)) : [];
        }

        static::$bootstrapCachedFiles[static::RESOLVING_EVENTS_PHP]['beforeResolving'][$abstract] = [];

        foreach ($this->beforeResolvingCallbacks as $type => $callbacks) {
            if ($type === $abstract || \is_subclass_of($abstract, $type)) {
                static::$bootstrapCachedFiles[static::RESOLVING_EVENTS_PHP]['beforeResolving'][$abstract][$type] = 0;
            }
        }

        return $this->getBeforeResolvingCallbacksForType($abstract);
    }

    /**
     * Get all resolving callbacks for a given type.
     */
    protected function getResolvingCallbacksForType(mixed $abstract, mixed $object): array
    {
        if ([] === $this->resolvingCallbacks || !\is_string($abstract)) {
            return [];
        }

        $cacheKey = $abstract . '___' . (\is_object($object) ? $object::class : \gettype($object));

        if (
            isset(
                static::getCachedFileContentsFromMemory(static::RESOLVING_EVENTS_PHP)['resolving'][$cacheKey]
            )
        ) {
            if ([] === static::$bootstrapCachedFiles[static::RESOLVING_EVENTS_PHP]['resolving'][$cacheKey]) {
                return [];
            }

            $groups = \array_intersect_key(
                $this->resolvingCallbacks,
                static::$bootstrapCachedFiles[static::RESOLVING_EVENTS_PHP]['resolving'][$cacheKey]
            );

            return $groups !== [] ? \array_merge(...\array_values($groups)) : [];
        }

        static::$bootstrapCachedFiles[static::RESOLVING_EVENTS_PHP]['resolving'][$cacheKey] = [];

        foreach ($this->resolvingCallbacks as $type => $callbacks) {
            if ($type === $abstract || $object instanceof $type) {
                static::$bootstrapCachedFiles[static::RESOLVING_EVENTS_PHP]['resolving'][$cacheKey][$type] = 0;
            }
        }

        return $this->getResolvingCallbacksForType($abstract, $object);
    }

    /**
     * Get all after resolving callbacks for a given type.
     */
    protected function getAfterResolvingCallbacksForType(mixed $abstract, mixed $object): array
    {
        if ([] === $this->afterResolvingCallbacks || !\is_string($abstract)) {
            return [];
        }

        $cacheKey = $abstract . '___' . (\is_object($object) ? $object::class : \gettype($object));

        if (
            isset(
                static::getCachedFileContentsFromMemory(static::RESOLVING_EVENTS_PHP)['afterResolving'][$cacheKey]
            )
        ) {
            if ([] === static::$bootstrapCachedFiles[static::RESOLVING_EVENTS_PHP]['afterResolving'][$cacheKey]) {
                return [];
            }

            $groups = \array_intersect_key(
                $this->afterResolvingCallbacks,
                static::$bootstrapCachedFiles[static::RESOLVING_EVENTS_PHP]['afterResolving'][$cacheKey]
            );

            return $groups !== [] ? \array_merge(...\array_values($groups)) : [];
        }

        static::$bootstrapCachedFiles[static::RESOLVING_EVENTS_PHP]['afterResolving'][$cacheKey] = [];

        foreach ($this->afterResolvingCallbacks as $type => $callbacks) {
            if ($type === $abstract || $object instanceof $type) {
                static::$bootstrapCachedFiles[static::RESOLVING_EVENTS_PHP]['afterResolving'][$cacheKey][$type] = 0;
            }
        }

        return $this->getAfterResolvingCallbacksForType($abstract, $object);
    }

    /**
     * Fire an array of callbacks with an object.
     *
     * @param mixed $object
     * @param array $callbacks
     * @return void
     */
    protected function fireCallbackArray($object, array $callbacks)
    {
        foreach ($callbacks as $callback) {
            $callback($object, $this);
        }
    }

    /**
     * Get the container's bindings.
     *
     * @return array
     */
    public function getBindings()
    {
        return $this->bindings;
    }

    /**
     * Get the alias for an abstract if available.
     *
     * @param string $abstract
     * @return string
     */
    public function getAlias($abstract)
    {
        if (!\is_string($abstract) || !isset($this->aliases[$abstract])) {
            return $abstract;
        }

        if (isset($this->alreadyRetrievedAliases[$abstract])) {
            return $this->alreadyRetrievedAliases[$abstract];
        }

        $k = $abstract;

        while (isset($this->aliases[$abstract])) {
            $abstract = $this->aliases[$abstract];
        }

        return $this->alreadyRetrievedAliases[$k] = $abstract;
    }

    /**
     * Get the extender callbacks for a given type.
     *
     * @param string $abstract
     * @return array
     */
    protected function getExtenders($abstract)
    {
        return $this->extenders[$this->getAlias($abstract)] ?? [];
    }

    /**
     * Remove all the extender callbacks for a given type.
     *
     * @param string $abstract
     * @return void
     */
    public function forgetExtenders($abstract)
    {
        unset($this->extenders[$this->getAlias($abstract)]);
    }

    /**
     * Drop all the stale instances and aliases.
     *
     * @param string $abstract
     * @return void
     */
    protected function dropStaleInstances($abstract)
    {
        unset($this->instances[$abstract], $this->aliases[$abstract]);
        $this->alreadyRetrievedAliases = [];
    }

    /**
     * Remove a resolved instance from the instance cache.
     *
     * @param string $abstract
     * @return void
     */
    public function forgetInstance($abstract)
    {
        unset($this->instances[$abstract]);
    }

    /**
     * Clear all the instances from the container.
     *
     * @return void
     */
    public function forgetInstances()
    {
        $this->instances = [];
    }

    /**
     * Clear all the scoped instances from the container.
     *
     * @return void
     */
    public function forgetScopedInstances()
    {
        foreach ($this->scopedInstances as $scoped) {
            unset($this->instances[$scoped]);
        }
    }

    /**
     * Flush the container of all bindings and resolved instances.
     *
     * @return void
     */
    public function flush()
    {
        $this->aliases = [];
        $this->alreadyRetrievedAliases = [];
        $this->resolved = [];
        $this->bindings = [];
        $this->instances = [];
        $this->abstractAliases = [];
        $this->scopedInstances = [];
    }

    /**
     * Get the globally available instance of the container.
     *
     * @return static
     */
    public static function getInstance()
    {
        if (!isset(static::$instance)) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * Set the shared instance of the container.
     *
     * @param \MacropaySolutions\Kernel\Contracts\Container\Container|null $container
     * @return \MacropaySolutions\Kernel\Contracts\Container\Container|static
     */
    public static function setInstance(?ContainerContract $container = null)
    {
        return static::$instance = $container;
    }

    public static function setBootstrapCacheFiles(string $cacheDir): void
    {
        static::$bootstrapCachedFiles = static::getBootstrapCachedFiles($cacheDir);
    }

    protected static function getBootstrapCachedFiles(string $cacheDir): ?array
    {
        if (\file_exists($cacheDir . DIRECTORY_SEPARATOR . static::MERGED_CACHED_FILES_PHP)) {
            return require $cacheDir . DIRECTORY_SEPARATOR . static::MERGED_CACHED_FILES_PHP;
        }

        return \is_dir($cacheDir) ?
            (\is_array($cacheDir = \scandir($cacheDir)) ? \array_flip($cacheDir) : null) :
            null;
    }

    /**
     * Determine if a given offset exists.
     *
     * @param string $offset
     * @return bool
     */
    public function offsetExists($offset): bool
    {
        return $this->bound($offset);
    }

    /**
     * Get the value at a given offset.
     *
     * @param string $offset
     * @return mixed
     */
    public function offsetGet($offset): mixed
    {
        return $this->make($offset);
    }

    /**
     * Set the value at a given offset.
     *
     * @param string $offset
     * @param mixed $value
     * @return void
     */
    public function offsetSet($offset, $value): void
    {
        $this->bind($offset, $value instanceof Closure ? $value : static fn() => $value);
    }

    /**
     * Unset the value at a given offset.
     *
     * @param string $offset
     * @return void
     */
    public function offsetUnset($offset): void
    {
        unset($this->bindings[$offset], $this->instances[$offset], $this->resolved[$offset]);
    }

    /**
     * Dynamically access container services.
     *
     * @param string $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this[$key];
    }

    /**
     * Dynamically set container services.
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function __set($key, $value)
    {
        $this[$key] = $value;
    }

    /**
     * Get the path to the bootstrap directory.
     *
     * @param string $path
     * @return string
     */
    public function bootstrapPath($path = '')
    {
        throw new \Exception(__FUNCTION__ . ' not implemented');
    }

    /**
     * Get the path to the cached services.php file.
     *
     * @return string
     */
    public function getCachedServicesPath()
    {
        return $this->bootstrapPath('cache' . DIRECTORY_SEPARATOR . static::SERVICES_PHP);
    }

    /**
     * Determine if the application configuration is cached.
     *
     * @return bool
     */
    public function configurationIsCached()
    {
        return isset(static::$bootstrapCachedFiles[static::CONFIG_PHP]);
    }

    /**
     * Get the path to the configuration cache file.
     *
     * @return string
     */
    public function getCachedConfigPath()
    {
        return $this->bootstrapPath('cache' . DIRECTORY_SEPARATOR . static::CONFIG_PHP);
    }

    /**
     * Determine if the application routes are cached.
     *
     * @return bool
     */
    public function routesAreCached()
    {
        return isset(static::$bootstrapCachedFiles[static::ROUTES_PHP]);
    }

    /**
     * Get the path to the routes cache file.
     *
     * @return string
     */
    public function getCachedRoutesPath()
    {
        return $this->bootstrapPath('cache' . DIRECTORY_SEPARATOR . static::ROUTES_PHP);
    }

    public function autowiringIsCached(): bool
    {
        return isset(static::$bootstrapCachedFiles[static::AUTOWIRING_PHP]);
    }

    public function abstractToTypeOfResolvingCallbacksEventsAsKeysIsCached(): bool
    {
        return isset(static::$bootstrapCachedFiles[static::RESOLVING_EVENTS_PHP]);
    }

    public function getCachedAutowiringPath(): string
    {
        return $this->bootstrapPath('cache' . DIRECTORY_SEPARATOR . static::AUTOWIRING_PHP);
    }

    public function getMergedCachedFilesPath(): string
    {
        return $this->bootstrapPath('cache' . DIRECTORY_SEPARATOR . static::MERGED_CACHED_FILES_PHP);
    }

    public function getCachedAbstractToTypeOfResolvingCallbacksEventsAsKeysPath(): string
    {
        return $this->bootstrapPath('cache' . DIRECTORY_SEPARATOR . static::RESOLVING_EVENTS_PHP);
    }

    /**
     * Determine if the application events are cached.
     *
     * @return bool
     */
    public function eventsAreCached()
    {
        return isset(static::$bootstrapCachedFiles[static::EVENTS_PHP]);
    }

    /**
     * Determine if the application events as observers are cached.
     */
    public function eventsAsObserversAreCached(): bool
    {
        return isset(static::$bootstrapCachedFiles[static::OBSERVERS_PHP]);
    }

    public static function issetBootstrapCacheFileKey(string $fileName): bool
    {
        return isset(static::$bootstrapCachedFiles[$fileName]);
    }

    /**
     * Determine if the application commands are cached.
     */
    public function commandsAreCached(): bool
    {
        return isset(static::$bootstrapCachedFiles[static::COMMANDS_PHP]);
    }

    /**
     * Get the path to the events cache file.
     *
     * @return string
     */
    public function getCachedEventsPath()
    {
        return $this->bootstrapPath('cache' . DIRECTORY_SEPARATOR . static::EVENTS_PHP);
    }

    /**
     * Get the path to the events as observers cache file.
     */
    public function getCachedEventsAsObserversPath(): string
    {
        return $this->bootstrapPath('cache' . DIRECTORY_SEPARATOR . static::OBSERVERS_PHP);
    }

    /**
     * Get the path to the commands cache file.
     */
    public function getCachedCommandsPath(): string
    {
        return $this->bootstrapPath('cache' . DIRECTORY_SEPARATOR . static::COMMANDS_PHP);
    }

    public static function getCachedFileContentsFromMemory(string $fileName): ?array
    {
        if (\is_array(static::$bootstrapCachedFiles[$fileName] ?? null)) {
            return static::$bootstrapCachedFiles[$fileName];
        }

        $app = static::getInstance();

        if ($fileName === static::AUTOWIRING_PHP && $app->autowiringIsCached()) {
            return static::$bootstrapCachedFiles[$fileName] = require $app->getCachedAutowiringPath();
        }

        if (
            $fileName === static::RESOLVING_EVENTS_PHP
            && $app->abstractToTypeOfResolvingCallbacksEventsAsKeysIsCached()
        ) {
            return static::$bootstrapCachedFiles[$fileName] =
                require $app->getCachedAbstractToTypeOfResolvingCallbacksEventsAsKeysPath();
        }

        return null;
    }
}
