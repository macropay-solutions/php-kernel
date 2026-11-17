<?php

namespace MacropaySolutions\Kernel\Tests\Support;

use MacropaySolutions\Kernel\Support\Collection;
use MacropaySolutions\Kernel\Support\HtmlString;
use MacropaySolutions\Kernel\Support\LazyCollection;
use PHPUnit\Framework\TestCase;

class CollectionLazyItemsAndStringableTest extends TestCase
{
    public function testCollapseAndFlattenKeepLazyCollections(): void
    {
        $this->assertSame([1, 2, 3], (new Collection([new LazyCollection([1, 2]), [3]]))->collapse()->all());
        $this->assertSame([1, 2, 3], (new Collection([new LazyCollection([1, [2]]), 3]))->flatten()->all());
    }

    public function testImplodeOfStringableObjects(): void
    {
        $this->assertSame('a,b', (new Collection([new HtmlString('a'), new HtmlString('b')]))->implode(','));
        $this->assertSame('1-2', (new Collection([['x' => 1], ['x' => 2]]))->implode('x', '-'));
    }

    public function testContainsStrictMatchesANullItem(): void
    {
        $this->assertTrue((new Collection([null, 1]))->containsStrict(static fn($value) => null === $value));
        $this->assertTrue((new LazyCollection([null, 1]))->containsStrict(static fn($value) => null === $value));
        $this->assertFalse((new Collection([1]))->containsStrict(static fn($value) => null === $value));
    }
}
