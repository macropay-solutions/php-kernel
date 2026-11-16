<?php

namespace MacropaySolutions\Kernel\Tests\Support;

use PHPUnit\Framework\TestCase;

/**
 * data_set() without overwrite (data_fill()) did not set the value under a null, and replaced an existing scalar
 * with an empty array.
 */
class DataFillTest extends TestCase
{
    public function testFillUnderNull(): void
    {
        $data = ['a' => null];
        data_fill($data, 'a.b', 1);
        $this->assertSame(['a' => ['b' => 1]], $data);

        $empty = null;
        data_fill($empty, 'x', 1);
        $this->assertSame(['x' => 1], $empty);
    }

    public function testAnExistingScalarIsKept(): void
    {
        $data = ['a' => 'keep'];
        data_fill($data, 'a.b', 1);
        $this->assertSame(['a' => 'keep'], $data);

        data_set($data, 'a.b', 1);
        $this->assertSame(['a' => ['b' => 1]], $data);
    }
}
