<?php

namespace MacropaySolutions\Kernel\Macroable\Contracts;

interface Macroable
{
    public static function deferredMacro(string $name, array $callableMethod): void;
    public static function hasMacro(string $name): bool;
    public static function flushMacros(): void;
    public function __call(string $method, array $parameters): mixed;
}
