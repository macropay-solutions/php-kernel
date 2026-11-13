<?php

namespace MacropaySolutions\Kernel\Tests\Pagination;

use MacropaySolutions\Kernel\Pagination\Cursor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Cursor::fromEncoded() gets its input from the request: anything that is not a cursor this class
 * encoded itself must give null (first page), never a warning, an Error or a non-scalar parameter.
 */
class CursorFromEncodedTest extends TestCase
{
    public function testRoundTripKeepsParametersAndDirection(): void
    {
        $cursor = Cursor::fromEncoded((new Cursor(['id' => 5, 'name' => 'a', 'deleted_at' => null], false))->encode());

        $this->assertInstanceOf(Cursor::class, $cursor);
        $this->assertSame(5, $cursor->parameter('id'));
        $this->assertSame('a', $cursor->parameter('name'));
        $this->assertNull($cursor->parameter('deleted_at'));
        $this->assertTrue($cursor->pointsToPreviousItems());
    }

    #[DataProvider('invalidCursorsProvider')]
    public function testInvalidCursorGivesNull(?string $encoded): void
    {
        $this->assertNull(Cursor::fromEncoded($encoded));
    }

    public static function invalidCursorsProvider(): array
    {
        $encode = static fn(string $json): string => \str_replace(['+', '/', '='], ['-', '_', ''], \base64_encode($json));

        return [
            'null (getFiltered() of an array)' => [null],
            'empty string (missing cursor)' => [''],
            'not base64' => ['%%%'],
            'not json' => [$encode('not json')],
            'json scalar' => [$encode('1')],
            'json string' => [$encode('"abc"')],
            'missing direction' => [$encode('{"id":1}')],
            'non bool direction' => [$encode('{"id":1,"_pointsToNextItems":"yes"}')],
            'array parameter' => [$encode('{"id":[1,2],"_pointsToNextItems":true}')],
            'object parameter' => [$encode('{"id":{"a":1},"_pointsToNextItems":true}')],
        ];
    }
}
