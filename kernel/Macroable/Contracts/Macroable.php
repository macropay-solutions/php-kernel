<?php

namespace MacropaySolutions\Kernel\Macroable\Contracts;

interface Macroable
{
    public static function deferredMacro(string $method, array $callableMethod): void;
    public static function hasMacro(string $method): bool;
    public static function flushMacros(): void;
    public function __call(string $method, array $parameters): mixed;
}
