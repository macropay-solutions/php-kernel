<?php

namespace MacropaySolutions\Kernel\Support\Traits;

trait CompiledMacroable
{
    /**
     * Register a custom deferred macro.
     * $callableMethod must be an array callable that resolves to a static method and returns the macro closure.
     */
    public static function deferredMacro(string $method, array $callableMethod): void
    {
    }

    /**
     * Checks if macro is registered.
     */
    public static function hasMacro(string $method): bool
    {
        return \method_exists(static::class, $method);
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
