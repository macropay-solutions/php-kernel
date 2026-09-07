<?php

namespace MacropaySolutions\Kernel\Macroable\Traits;

trait CompiledMacroable
{
    /**
     * The registered string macros.
     */
    protected static array $macros = [];

    /**
     * Register a custom macro.
     */
    private static function macro(string $name, callable|object $macro): void
    {
    }

    /**
     * Register a custom deferred macro.
     * $callableMethod must be array callable that resolves to a static method and returns the macro closure.
     */
    public static function deferredMacro(string $name, array $callableMethod): void
    {
    }

    /**
     * Checks if macro is registered.
     */
    public static function hasMacro(string $name): bool
    {
        return \method_exists(static::class, $name);
    }

    /**
     * Flush the existing macros.
     */
    public static function flushMacros(): void
    {
    }

    public function __call(string $method, array $parameters): mixed
    {
        return null;
    }
}
