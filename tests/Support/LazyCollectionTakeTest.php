<?php

namespace MacropaySolutions\Kernel\Tests\Support;

use MacropaySolutions\Kernel\Support\LazyCollection;
use PHPUnit\Framework\TestCase;

/**
 * take() with a negative limit returns the last N items, keeping their keys.
 * Regression: a negative limit larger than the collection read empty ring-buffer slots.
 */
class LazyCollectionTakeTest extends TestCase
{
    public function testNegativeLimitLargerThanCollectionReturnsAllItems(): void
    {
        $this->assertSame(['a', 'b'], LazyCollection::make(['a', 'b'])->take(-5)->all());
    }

    public function testNegativeLimitLargerThanGeneratorReturnsAllItemsWithKeys(): void
    {
        $source = static function (): \Generator {
            yield 'x' => 1;
            yield 'y' => 2;
        };

        $this->assertSame(['x' => 1, 'y' => 2], LazyCollection::make($source)->take(-5)->all());
    }

    public function testNegativeLimitSmallerThanCollectionReturnsLastItemsWithKeys(): void
    {
        $this->assertSame([3 => 'd', 4 => 'e'], LazyCollection::make(['a', 'b', 'c', 'd', 'e'])->take(-2)->all());
    }

    public function testNegativeLimitWrappingTheBufferKeepsOrder(): void
    {
        $this->assertSame(
            [4 => 'e', 5 => 'f', 6 => 'g'],
            LazyCollection::make(['a', 'b', 'c', 'd', 'e', 'f', 'g'])->take(-3)->all()
        );
    }

    public function testNegativeLimitEqualToCollectionReturnsAllItems(): void
    {
        $this->assertSame(['a', 'b', 'c'], LazyCollection::make(['a', 'b', 'c'])->take(-3)->all());
    }

    public function testNegativeLimitOnEmptyCollectionReturnsNothing(): void
    {
        $this->assertSame([], LazyCollection::make([])->take(-3)->all());
    }
}
