<?php

namespace MacropaySolutions\Kernel\Support\Traits;

use BadMethodCallException;
use Closure;

trait Macroable
{
    /**
     * The registered string macros.
     */
    protected static array $macros = [];

    /**
     * The registered string deferred macros.
     */
    protected static array $deferredMacros = [];

    /**
     * Register a custom macro.
     */
    private static function macro(string $name, callable|object $macro): void
    {
        if (!$macro instanceof Closure) {
            static::$macros[$name] = $macro;

            return;
        }

        try {
            static::$macros[$name] = $macro->bindTo(null, static::class) ?? $macro;
        } catch (\Throwable) {
            // Keep original closure if locked by php if inside a nonstatic closure already (db transaction closure)
            static::$macros[$name] = $macro;
        }
    }

    /**
     * Register a custom deferred macro.
     * $callableMethod must be array callable that resolves to a static method and returns the macro closure.
     */
    public static function deferredMacro(string $name, array $callableMethod): void
    {
        if ( !\is_callable($callableMethod)) {
            throw new \RuntimeException('deferredMacro requires an array callable in [Class, method] format');
        }

        static::$deferredMacros[$name] = $callableMethod;
    }

    /**
     * Checks if macro is registered.
     *
     * @param string $name
     * @return bool
     */
    public static function hasMacro($name)
    {
        return isset(static::$deferredMacros[$name]) || isset(static::$macros[$name]);
    }

    /**
     * Flush the existing macros.
     */
    public static function flushMacros(): void
    {
        static::$macros = [];
        static::$deferredMacros = [];
    }

    /**
     * Dynamically handle calls to the class.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     *
     * @throws \BadMethodCallException
     */
    public static function __callStatic($method, $parameters)
    {
        if (!static::hasMacro($method)) {
            throw new BadMethodCallException(
                sprintf(
                    'Method %s::%s does not exist.',
                    static::class,
                    $method
                )
            );
        }

        if (isset(static::$deferredMacros[$method])) {
            static::macro($method, static::$deferredMacros[$method]());
            unset(static::$deferredMacros[$method]);
        }

        return static::$macros[$method](...$parameters);
    }

    /**
     * Dynamically handle calls to the class.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     *
     * @throws \BadMethodCallException
     */
    public function __call($method, $parameters)
    {
        if (!static::hasMacro($method)) {
            throw new BadMethodCallException(
                sprintf(
                    'Method %s::%s does not exist.',
                    static::class,
                    $method
                )
            );
        }

        if (isset(static::$deferredMacros[$method])) {
            static::macro($method, static::$deferredMacros[$method]());
            unset(static::$deferredMacros[$method]);
        }

        $macro = static::$macros[$method];

        if ($macro instanceof Closure) {
            return $macro->call($this, ...$parameters);
        }

        return $macro(...$parameters);
    }
}
