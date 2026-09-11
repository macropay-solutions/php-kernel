<?php

namespace MacropaySolutions\Kernel\Macroable\Traits;

trait ExplicitForwardable
{
    public function __call(string $method, array $parameters): mixed
    {
        $caller = \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0] ?? [];
        $file = $caller['file'] ?? 'unknown file';
        $line = $caller['line'] ?? 0;

        throw new \BadMethodCallException(sprintf(
            'Magic call ->%s() is disabled. Use ->fwd()->%s() instead in %s:%d',
            $method,
            $method,
            $file,
            $line
        ));
    }
}