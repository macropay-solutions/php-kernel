<?php

namespace MacropaySolutions\Kernel\Tests\Support;

use MacropaySolutions\Kernel\Support\Arr;
use MacropaySolutions\Kernel\Support\Collection;
use MacropaySolutions\Kernel\Support\LazyCollection;
use PHPUnit\Framework\TestCase;

/**
 * Collection edge cases that returned wrong values instead of failing.
 */
class CollectionEdgeCasesTest extends TestCase
{
    public function testModeIgnoresNulls(): void
    {
        $this->assertSame([1], (new Collection([1, 1, null, null, null, 2]))->mode());
        $this->assertNull((new Collection([null, null]))->mode());
    }

    public function testPopWithACountBelowOneRemovesNothing(): void
    {
        $collection = new Collection([1, 2, 3]);

        $this->assertSame([], $collection->pop(0)->all());
        $this->assertSame([], $collection->pop(-1)->all());
        $this->assertSame([1, 2, 3], $collection->all());
    }

    public function testLazyHasCountsEachKeyOnce(): void
    {
        $lazy = new LazyCollection(function () {
            yield 'a' => 1;
            yield 'a' => 2;
        });

        $this->assertFalse($lazy->has(['a', 'b']));
        $this->assertTrue($lazy->has('a'));
    }

    public function testWhereBetweenAcceptsACollectionOfBounds(): void
    {
        $collection = new Collection([['v' => 1], ['v' => 5], ['v' => 9]]);

        $this->assertSame([['v' => 5]], $collection->whereBetween('v', new Collection([2, 6]))->values()->all());
        $this->assertSame(
            [['v' => 1], ['v' => 9]],
            $collection->whereNotBetween('v', new Collection([2, 6]))->values()->all()
        );
    }

    public function testArrLastReadsTraversables(): void
    {
        $this->assertSame(3, Arr::last(new Collection([1, 2, 3])));
        $this->assertSame(2, Arr::last(new \ArrayObject([1, 2, 3]), fn($value) => $value < 3));
        $this->assertSame('default', Arr::last(new Collection(), null, 'default'));
    }
}
