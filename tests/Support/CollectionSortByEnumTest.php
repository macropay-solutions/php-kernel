<?php

namespace MacropaySolutions\Kernel\Tests\Support;

use MacropaySolutions\Kernel\Support\Collection;
use PHPUnit\Framework\TestCase;

/**
 * Enum cases cannot be compared with each other, so sortBy() on an enum attribute gave a wrong order.
 */
class CollectionSortByEnumTest extends TestCase
{
    public function testSortByABackedEnum(): void
    {
        $items = new Collection([
            ['id' => 1, 'status' => CollectionSortByEnumTestStatus::C],
            ['id' => 2, 'status' => CollectionSortByEnumTestStatus::A],
            ['id' => 3, 'status' => CollectionSortByEnumTestStatus::B],
        ]);

        $this->assertSame([2, 3, 1], $items->sortBy('status')->pluck('id')->all());
        $this->assertSame([1, 3, 2], $items->sortByDesc('status')->pluck('id')->all());
    }

    public function testSortByManyWithAnEnum(): void
    {
        $items = new Collection([
            ['id' => 1, 'status' => CollectionSortByEnumTestStatus::B, 'name' => 'y'],
            ['id' => 2, 'status' => CollectionSortByEnumTestStatus::A, 'name' => 'z'],
            ['id' => 3, 'status' => CollectionSortByEnumTestStatus::B, 'name' => 'x'],
            ['id' => 4, 'status' => CollectionSortByEnumTestStatus::C, 'name' => 'a'],
        ]);

        $this->assertSame(
            [2, 3, 1, 4],
            $items->sortBy([['status', 'asc'], ['name', 'asc']])->pluck('id')->all()
        );
        $this->assertSame(
            [4, 1, 3, 2],
            $items->sortBy([['status', 'desc'], ['name', 'desc']])->pluck('id')->all()
        );
    }
}

enum CollectionSortByEnumTestStatus: string
{
    case A = 'a';
    case B = 'b';
    case C = 'c';
}
