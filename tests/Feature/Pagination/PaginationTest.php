<?php

namespace MacropaySolutions\Kernel\Tests\Feature\Pagination;

use MacropaySolutions\Kernel\Database\Obvious\Model;
use MacropaySolutions\Kernel\Database\Schema\Blueprint;
use MacropaySolutions\Kernel\Pagination\LengthAwarePaginator;
use MacropaySolutions\Kernel\Support\Collection;
use MacropaySolutions\Kernel\Tests\ApplicationTestCase;
use MacropaySolutions\Kernel\Tests\Fixtures\Database\Author;

/**
 * Length-aware, simple and cursor pagination over a real table, and explicit ->fwd() on paginators.
 */
class PaginationTest extends ApplicationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Model::unsetConnectionResolver();

        $this->db()->getSchemaBuilder()->create('authors', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->integer('rating')->nullable();
            $table->timestamps();
        });

        foreach (\range(1, 7) as $i) {
            $this->db()->table('authors')->insert(['name' => 'author-' . $i, 'created_at' => '2026-01-01 00:00:00']);
        }
    }

    protected function tearDown(): void
    {
        Model::unsetConnectionResolver();

        parent::tearDown();
    }

    public function test_length_aware_pagination(): void
    {
        $page = Author::query()->orderBy('id')->paginate(3, ['*'], 'page', 2);

        $this->assertInstanceOf(LengthAwarePaginator::class, $page);
        $this->assertSame(7, $page->total());
        $this->assertSame(3, $page->lastPage());
        $this->assertSame(2, $page->currentPage());
        $this->assertTrue($page->hasMorePages());
        $this->assertSame(['author-4', 'author-5', 'author-6'], $page->fwd()->pluck('name')->all());
        $this->assertStringContainsString('page=3', (string)$page->nextPageUrl());

        $array = $page->toArray();

        $this->assertSame(7, $array['total']);
        $this->assertSame(4, $array['from']);
        $this->assertSame(6, $array['to']);
        $this->assertCount(3, $array['data']);
    }

    public function test_the_last_page_has_no_next_page(): void
    {
        $page = Author::query()->orderBy('id')->paginate(3, ['*'], 'page', 3);

        $this->assertFalse($page->hasMorePages());
        $this->assertNull($page->nextPageUrl());
        $this->assertSame(['author-7'], $page->fwd()->pluck('name')->all());
    }

    public function test_simple_pagination_does_not_count(): void
    {
        $page = $this->db()->table('authors')->orderBy('id')->simplePaginate(5, ['*'], 'page', 1);

        $this->assertTrue($page->hasMorePages());
        $this->assertCount(5, $page->items());
        $this->assertArrayNotHasKey('total', $page->toArray());

        $last = $this->db()->table('authors')->orderBy('id')->simplePaginate(5, ['*'], 'page', 2);

        $this->assertFalse($last->hasMorePages());
        $this->assertCount(2, $last->items());
    }

    public function test_cursor_pagination_walks_every_row_once(): void
    {
        $names = [];
        $cursor = null;

        do {
            $page = Author::query()->orderBy('id')->cursorPaginate(3, ['*'], 'cursor', $cursor);
            $names = \array_merge($names, $page->fwd()->pluck('name')->all());
            $cursor = $page->nextCursor();
        } while ($cursor !== null);

        $this->assertSame(\array_map(static fn(int $i): string => 'author-' . $i, \range(1, 7)), $names);
    }

    public function test_paginators_only_forward_through_fwd(): void
    {
        $page = Author::query()->orderBy('id')->paginate(3, ['*'], 'page', 1);

        $this->assertInstanceOf(Collection::class, $page->fwd());

        $this->expectException(\BadMethodCallException::class);

        $page->pluck('name');
    }
}
