<?php

namespace Illuminate\Support\Traits;

use BadMethodCallException;
use Closure;
use ReflectionClass;
use ReflectionMethod;

trait Macroable
{
    /**
     * The registered string macros.
     *
     * @var array
     */
    protected static $macros = [];

    /**
     * Register a custom macro.
     *
     * @param string $name
     * @param object|callable $macro
     * @return void
     */
    public static function macro($name, $macro)
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
     * Mix another object into the class.
     *
     * @param object $mixin
     * @param bool $replace
     * @return void
     *
     * @throws \ReflectionException
     */
    public static function mixin($mixin, $replace = true)
    {
        $methods = (new ReflectionClass($mixin))->getMethods(
            ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED
        );

        foreach ($methods as $method) {
            if ($replace || !static::hasMacro($method->name)) {
                static::macro($method->name, $method->invoke($mixin));
            }
        }
    }

    /**
     * Checks if macro is registered.
     *
     * @param string $name
     * @return bool
     */
    public static function hasMacro($name)
    {
        return isset(static::$macros[$name]);
    }

    /**
     * Flush the existing macros.
     *
     * @return void
     */
    public static function flushMacros()
    {
        static::$macros = [];
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

        $macro = static::$macros[$method];

        if ($macro instanceof Closure) {
            return $macro->call($this, ...$parameters);
        }

        return $macro(...$parameters);
    }
}
