<?php

namespace MacropaySolutions\Kernel\Tests\Feature\Collections;

use MacropaySolutions\Kernel\Support\Collection;
use MacropaySolutions\Kernel\Support\LazyCollection;
use MacropaySolutions\Kernel\Support\MultipleItemsFoundException;
use PHPUnit\Framework\TestCase;

/**
 * sole(), firstOrFail() and times() used unless() without a callback, which threw an ArgumentCountError.
 */
class NullFilterTest extends TestCase
{
    public function test_sole_without_filter_returns_the_only_item_even_when_it_is_falsy(): void
    {
        $this->assertSame(0, (new Collection([0]))->sole());
    }

    public function test_sole_without_filter_throws_for_multiple_items(): void
    {
        $this->expectException(MultipleItemsFoundException::class);

        (new Collection([1, 2]))->sole();
    }

    public function test_sole_with_filter(): void
    {
        $this->assertSame(2, (new Collection([1, 2, 3]))->sole(fn(int $value): bool => $value === 2));
    }

    public function test_times_without_and_with_callback(): void
    {
        $this->assertSame([1, 2, 3], Collection::times(3)->all());
        $this->assertSame([10, 20, 30], Collection::times(3, fn(int $number): int => $number * 10)->all());
    }

    public function test_lazy_sole_and_first_or_fail_without_filter(): void
    {
        $this->assertSame(0, LazyCollection::make([0])->sole());
        $this->assertSame(4, LazyCollection::make([4, 5])->firstOrFail());
    }

    public function test_lazy_sole_and_first_or_fail_with_filter(): void
    {
        $this->assertSame(3, LazyCollection::make([1, 2, 3])->sole(fn(int $value): bool => $value === 3));
        $this->assertSame(2, LazyCollection::make([1, 2, 3])->firstOrFail(fn(int $value): bool => $value > 1));
    }
}
