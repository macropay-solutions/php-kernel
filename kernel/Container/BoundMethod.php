<?php

namespace MacropaySolutions\Kernel\Container;

use Closure;
use InvalidArgumentException;
use MacropaySolutions\Kernel\Console\DiscoverAutowiring;
use MacropaySolutions\Kernel\Contracts\Container\BindingResolutionException;
use ReflectionFunction;
use ReflectionMethod;

class BoundMethod
{
    protected static bool $areEnabledClassesFqnsToCacheForAutowire = false;

    /**
     * Contains classesFQN as keys
     */
    protected static array $classesFqnsToCacheForAutowire = [];

    /**
     * [
     *   '{classFqn}' => [
     *     '{methodName}' => [
     *       '{paramName1}' => [
     *         'c' => string, // can not exist
     *         'v' => bool, // can not exist
     *         'o' => bool, // can not exist
     *         'd' => mixed, // can not exist
     *       ],
     *       '{paramName2}' => [
     *         'c' => string, // can not exist
     *         'v' => bool, // can not exist
     *         'o' => bool, // can not exist
     *         'd' => mixed, // can not exist
     *       ],
     *     ]
     *   ]
     * ]
     * If the method has variadic or with default value
     */
    protected static ?array $precompiledAutoWiringClassMethodParametersMap = null;

    /**
     * @deprecated see Container::getCachedFileContentsFromMemory(Container::AUTOWIRING_PHP)
     */
    public static function setPrecompiledAutoWiringClassMethodParametersMap(array $map): void
    {
    }

    public static function getPrecompiledAutoWiringClassMethodParametersMap(): ?array
    {
        return static::$precompiledAutoWiringClassMethodParametersMap;
    }

    public static function getAndCachePrecompiledAutoWiringClassMethodParametersMapForClassAndMethod(
        string $class,
        string $method
    ): array {
        return Container::getCachedFileContentsFromMemory(Container::AUTOWIRING_PHP)[$class][$method] ??
            (static::$precompiledAutoWiringClassMethodParametersMap[$class][$method] ??= (function (
                string $class,
                string $method
            ): array {
                if (!\in_array($method, \get_class_methods($class) ?? [], true)) {
                    return [];
                }

                $parameters = [];

                foreach ((new \ReflectionMethod($class, $method))->getParameters() as $p) {
                    $parameters[$p->getName()] = DiscoverAutowiring::getParameterDetails($p);
                }

                return $parameters;
            })($class, $method));
    }


    /**
     * Call the given Closure / class@method and inject its dependencies.
     *
     * @param \MacropaySolutions\Kernel\Container\Container $container
     * @param callable|array|string $callback
     * @param array $parameters
     * @param string|null $defaultMethod
     * @return mixed
     *
     * @throws \ReflectionException
     * @throws \InvalidArgumentException
     */
    public static function call($container, $callback, array $parameters = [], $defaultMethod = null)
    {
        if (is_string($callback) && !$defaultMethod && method_exists($callback, '__invoke')) {
            $defaultMethod = '__invoke';
        }

        if (static::isCallableWithAtSign($callback) || $defaultMethod) {
            return static::callClass($container, $callback, $parameters, $defaultMethod);
        }

        if (\is_array($callback) && \is_string($callback[0])) {
            // If it's not natively callable as a static array, instantiate the class.
            if (!\is_callable($callback)) {
                $callback[0] = $container->make($callback[0]);
            }
        }

        return static::callBoundMethod($container, $callback, function () use ($container, $callback, $parameters) {
            return $callback(...array_values(static::getMethodDependencies($container, $callback, $parameters)));
        });
    }

    /**
     * Get all dependencies for construct.
     *
     * @throws \ReflectionException
     * @throws BindingResolutionException
     */
    public static function getConstructDependencies(
        \MacropaySolutions\Kernel\Container\Container $container,
        string $classFqn,
        array $assocArrayParameters = []
    ): array {
        return static::getMethodDependencies($container, $classFqn . '::__construct', $assocArrayParameters);
    }

    /**
     * Call a string reference to a class using Class@method syntax.
     *
     * @param \MacropaySolutions\Kernel\Container\Container $container
     * @param string $target
     * @param array $parameters
     * @param string|null $defaultMethod
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    protected static function callClass($container, $target, array $parameters = [], $defaultMethod = null)
    {
        $segments = explode('@', $target);

        // We will assume an @ sign is used to delimit the class name from the method
        // name. We will split on this @ sign and then build a callable array that
        // we can pass right back into the "call" method for dependency binding.
        $method = count($segments) === 2
            ? $segments[1] : $defaultMethod;

        if (is_null($method)) {
            throw new InvalidArgumentException('Method not provided.');
        }

        return static::call(
            $container,
            [$container->make($segments[0]), $method],
            $parameters
        );
    }

    /**
     * Call a method that has been bound to the container.
     *
     * @param \MacropaySolutions\Kernel\Container\Container $container
     * @param callable $callback
     * @param mixed $default
     * @return mixed
     */
    protected static function callBoundMethod($container, $callback, $default)
    {
        if (!is_array($callback)) {
            return Util::unwrapIfClosure($default);
        }

        // Here we need to turn the array callable into a Class@method string we can use to
        // examine the container and see if there are any method bindings for this given
        // method. If there are, we can call this method binding callback immediately.
        $method = static::normalizeMethod($callback);

        if ($container->hasMethodBinding($method)) {
            return $container->callMethodBinding($method, $callback[0]);
        }

        return Util::unwrapIfClosure($default);
    }

    /**
     * Normalize the given callback into a Class@method string.
     *
     * @param callable $callback
     * @return string
     */
    protected static function normalizeMethod($callback)
    {
        $class = is_string($callback[0]) ? $callback[0] : get_class($callback[0]);

        return "{$class}@{$callback[1]}";
    }

    /**
     * Get all dependencies for a given method.
     *
     * @param \MacropaySolutions\Kernel\Container\Container $container
     * @param callable|string $callback
     * @param array $parameters
     * @return array
     *
     * @throws \ReflectionException
     * @throws BindingResolutionException
     */
    protected static function getMethodDependencies($container, $callback, array $parameters = [])
    {
        $dependencies = [];
        $callback = static::getPreparedCallback($callback);

        if (\is_array($callback)) {
            $classFqn = \reset($callback);

            if (\is_object($classFqn)) {
                $classFqn = $classFqn::class;
            }

            $method = \next($callback);

            if (
                \is_string($classFqn)
                && \is_string($method)
                && \is_array($a = (Container::getCachedFileContentsFromMemory(Container::AUTOWIRING_PHP) ?? [])[
                    $classFqn = \ltrim($classFqn, '\\')
                ][$method] ?? static::$precompiledAutoWiringClassMethodParametersMap[$classFqn][$method] ?? null)
            ) {
                foreach ($a as $name => $map) {
                    static::addDependencyWithoutReflectionForCallParameter(
                        $container,
                        $parameters,
                        $dependencies,
                        $name,
                        $map,
                        $classFqn,
                        $method
                    );
                }

                return \array_merge($dependencies, \array_values($parameters));
            }
        }

        foreach (static::getCallReflectorWithPreparedCallback($callback)->getParameters() as $parameter) {
            static::addDependencyForCallParameter($container, $parameter, $parameters, $dependencies);
        }

        return array_merge($dependencies, array_values($parameters));
    }

    /**
     * Get the proper reflection instance for the given callback.
     *
     * @throws \ReflectionException
     */
    protected static function getCallReflectorWithPreparedCallback(
        \Closure|string|array $callback
    ): \ReflectionFunctionAbstract {
        return is_array($callback)
            ? new ReflectionMethod(\reset($callback), \next($callback))
            : new ReflectionFunction($callback);
    }

    public static function enableClassesFqnsToCacheForAutowire(): void
    {
        static::$areEnabledClassesFqnsToCacheForAutowire = true;
    }

    public static function addToClassesFqnsToCacheForAutowire(string $concrete): void
    {
        if (static::$areEnabledClassesFqnsToCacheForAutowire) {
            static::$classesFqnsToCacheForAutowire[$concrete] = true;
        }
    }

    public static function getClassesFqnsToCacheForAutowire(): array
    {
        return static::$classesFqnsToCacheForAutowire;
    }

    /**
     * Get the proper reflection instance for the given callback.
     *
     * @param callable|string $callback
     * @return \ReflectionFunctionAbstract
     *
     * @throws \ReflectionException
     */
    protected static function getCallReflector($callback)
    {
        $callback = static::getPreparedCallback($callback);

        return is_array($callback)
            ? new ReflectionMethod($callback[0], $callback[1])
            : new ReflectionFunction($callback);
    }

    protected static function getPreparedCallback(object|string|array $callback): \Closure|string|array
    {
        if (is_string($callback) && str_contains($callback, '::')) {
            return \explode('::', $callback);
        }

        if (is_object($callback) && !$callback instanceof Closure) {
            return [$callback, '__invoke'];
        }

        return $callback;
    }

    /**
     * @throws BindingResolutionException
     * @see Container::getCachedFileContentsFromMemory(Container::AUTOWIRING_PHP) &
     *    static::$precompiledAutoWiringClassMethodParametersMap for $parameterMap
     *   [
     *     'c' => string, // can not exist
     *     'v' => bool, // can not exist
     *     'o' => bool, // can not exist
     *     'd' => mixed, // can not exist
     *   ]
     */
    protected static function addDependencyWithoutReflectionForCallParameter(
        \MacropaySolutions\Kernel\Container\Container $container,
        array &$parameters,
        array &$dependencies,
        string $name,
        array $parameterMap,
        string $classFqn,
        string $method,
    ): void {
        if (\array_key_exists($name, $parameters)) {
            $dependencies[] = $parameters[$name];
            unset($parameters[$name]);

            return;
        }

        if (isset($parameterMap['c'])) {
            if (\array_key_exists($parameterMap['c'], $parameters)) {
                $dependencies[] = $parameters[$parameterMap['c']];
                unset($parameters[$parameterMap['c']]);

                return;
            }

            try {
                if ($parameterMap['v'] ?? false) {
                    // is variadic
                    $dependencies = \array_merge($dependencies, (array)$container->make($parameterMap['c']));

                    return;
                }

                if (
                    $container::DEFAULT_PARAMETER_TAKES_PRECEDENCE_WHEN_AUTOWIRING
                    && \array_key_exists('d', $parameterMap)
                    && !$container->bound($parameterMap['c'])
                ) {
                    // has default
                    $dependencies[] = $parameterMap['d'];

                    return;
                }

                $dependencies[] = $container->make($parameterMap['c']);

                return;
            } catch (BindingResolutionException $bindingResolutionException) {
            }
        }

        if (\array_key_exists('d', $parameterMap)) {
            // has default
            $dependencies[] = $parameterMap['d'];

            return;
        }

        if (!($parameterMap['o'] ?? false)) {
            if (isset($bindingResolutionException)) {
                throw $bindingResolutionException;
            }

            // is not optional
            $message = 'Unable to resolve dependency [' . $name . '] in class ' . $classFqn . '::' . $method;

            throw new BindingResolutionException($message);
        }
    }

    /**
     * Get the dependency for the given call parameter.
     *
     * @param \MacropaySolutions\Kernel\Container\Container $container
     * @param \ReflectionParameter $parameter
     * @param array $parameters
     * @param array $dependencies
     * @return void
     *
     * @throws \MacropaySolutions\Kernel\Contracts\Container\BindingResolutionException
     */
    protected static function addDependencyForCallParameter(
        $container,
        $parameter,
        array &$parameters,
        &$dependencies
    ) {
        if (array_key_exists($paramName = $parameter->getName(), $parameters)) {
            $dependencies[] = $parameters[$paramName];

            unset($parameters[$paramName]);

            return;
        }

        if (\is_string($className = Util::getParameterClassName($parameter))) {
            if (\array_key_exists($className, $parameters)) {
                $dependencies[] = $parameters[$className];

                unset($parameters[$className]);

                return;
            }

            try {
                if ($parameter->isVariadic()) {
                    $dependencies = \array_merge($dependencies, (array)$container->make($className));

                    return;
                }

                $dependencies[] = $container->make($className);

                return;
            } catch (BindingResolutionException $bindingResolutionException) {
            }
        }

        if ($parameter->isDefaultValueAvailable()) {
            $dependencies[] = $parameter->getDefaultValue();

            return;
        }

        if (!$parameter->isOptional()) {
            if (isset($bindingResolutionException)) {
                throw $bindingResolutionException;
            }

            $message = "Unable to resolve dependency [{$parameter}] in class " .
                $parameter->getDeclaringClass()->getName();

            throw new BindingResolutionException($message);
        }
    }

    /**
     * Determine if the given string is in Class@method syntax.
     *
     * @param mixed $callback
     * @return bool
     */
    protected static function isCallableWithAtSign($callback)
    {
        return is_string($callback) && str_contains($callback, '@');
    }
}
