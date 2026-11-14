<?php

namespace MacropaySolutions\Kernel\Tests\Support;

use MacropaySolutions\Kernel\Support\Arr;
use PHPUnit\Framework\TestCase;

/**
 * Arr::forget() checked a top-level key against the nested array left referenced by a previous dotted key.
 */
class ArrForgetResetTest extends TestCase
{
    public function testTopLevelKeyAfterDottedKey(): void
    {
        $array = ['a' => ['b' => 1, 'c' => 2], 'c' => 3];

        Arr::forget($array, ['a.b', 'c']);

        $this->assertSame(['a' => ['c' => 2]], $array);
    }

    public function testDottedKeysInAnyOrder(): void
    {
        $array = ['a' => ['b' => 1, 'x' => 2], 'b' => 3, 'x' => 4];

        Arr::forget($array, ['a.x', 'x', 'a.b']);

        $this->assertSame(['a' => [], 'b' => 3], $array);
    }
}
