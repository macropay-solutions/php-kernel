<?php

namespace MacropaySolutions\Kernel\Support\Traits;

use BadMethodCallException;
use Closure;
use MacropaySolutions\Kernel\Container\Container;

trait Macroable
{
    /**
     * The registered string macros.
     */
    protected static array $macros = [];

    /**
     * Register a custom deferred macro.
     * $callableMethod must be an array callable that resolves to a static method and returns the macro closure.
     * @see CompiledMacroable::deferredMacro()
     * @see \MacropaySolutions\Kernel\Console\MacroCacheCommand::handle()
     */
    public static function deferredMacro(string $method, array $callableMethod): void
    {
        if (Container::getInstance()->isBooted()) {
            throw new \LogicException(
                'Deferred macros must be registered before the application has booted.'
            );
        }

        if (\method_exists(static::class, $method) || null !== static::getMacro($method)) {
            throw new \LogicException('Method already exists: ' . $method);
        }

        if (!\is_string($callableMethod[0] ?? null) || !\is_callable($callableMethod)) {
            throw new \RuntimeException('deferredMacro requires an array callable in [Class::class, method] format');
        }

        static::$macros[$method] = $callableMethod;
    }

    /**
     * Traverse the inheritance tree to find the class that registered the macro.
     */
    protected static function resolveMacro(string $method): null|array
    {
        $class = static::class;

        while ($class !== false) {
            if (isset($class::$macros[$method])) {
                return $class::$macros[$method];
            }

            $class = \get_parent_class($class);
        }

        return null;
    }

    protected static function getMacro(string $method): callable
    {
        $macro = static::resolveMacro($method);

        if (null === $macro) {
            throw new BadMethodCallException(
                \sprintf('Method %s::%s does not exist.', static::class, $method)
            );
        }

        return $macro();
    }

    /**
     * Checks if macro is registered on this class or any parent class.
     */
    public static function hasMacro(string $method): bool
    {
        return static::resolveMacro($method) !== null;
    }

    /**
     * Flush the existing macros.
     */
    public static function flushMacros(): void
    {
        static::$macros = [];
    }

    /**
     * Dynamically handle static calls to the class.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     *
     * @throws \BadMethodCallException
     */
    public static function __callStatic(string $method, array $parameters): mixed
    {
        return self::getMacro($method)(...$parameters);
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
    public function __call(string $method, array $parameters): mixed
    {
        $macro = self::getMacro($method);

        if ($macro instanceof Closure) {
            return $macro->call($this, ...$parameters);
        }

        return $macro(...$parameters);
    }
}