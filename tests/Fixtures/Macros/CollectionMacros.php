<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Macros;

use MacropaySolutions\Kernel\Support\Collection;

/**
 * Deferred macro factories: each returns the macro closure that macro:cache compiles into a native method.
 */
class CollectionMacros
{
    public static function doubled(): \Closure
    {
        return function (int $times = 2): Collection {
            return $this->map(static fn(int $item): int => $item * $times);
        };
    }

    public static function fromRange(): \Closure
    {
        return static function (int $from, int $to): Collection {
            return new Collection(\range($from, $to));
        };
    }
}
