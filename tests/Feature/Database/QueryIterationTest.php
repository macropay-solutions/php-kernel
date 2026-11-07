<?php

namespace MacropaySolutions\Kernel\Tests\Feature\Database;

use MacropaySolutions\Kernel\Database\Obvious\Model;
use MacropaySolutions\Kernel\Database\Schema\Blueprint;
use MacropaySolutions\Kernel\Support\LazyCollection;
use MacropaySolutions\Kernel\Tests\ApplicationTestCase;
use MacropaySolutions\Kernel\Tests\Fixtures\Database\Author;

/**
 * chunk, chunkById, each, lazy, lazyById and cursor visit every row exactly once, in order.
 */
class QueryIterationTest extends ApplicationTestCase
{
    private array $expected;

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

        $this->expected = \array_map(static fn(int $i): string => 'author-' . $i, \range(1, 7));
    }

    protected function tearDown(): void
    {
        Model::unsetConnectionResolver();

        parent::tearDown();
    }

    public function test_chunk_and_chunk_by_id(): void
    {
        $chunks = [];
        Author::query()->orderBy('id')->chunk(3, static function ($models) use (&$chunks): void {
            $chunks[] = $models->pluck('name')->all();
        });

        $this->assertSame(
            [['author-1', 'author-2', 'author-3'], ['author-4', 'author-5', 'author-6'], ['author-7']],
            $chunks
        );

        $names = [];
        Author::query()->chunkById(2, static function ($models) use (&$names): void {
            $names = \array_merge($names, $models->pluck('name')->all());
        });

        $this->assertSame($this->expected, $names);
    }

    public function test_chunk_stops_when_the_callback_returns_false(): void
    {
        $calls = 0;
        Author::query()->orderBy('id')->chunk(2, static function () use (&$calls): bool {
            $calls++;

            return false;
        });

        $this->assertSame(1, $calls);
    }

    public function test_chunk_by_id_is_safe_when_rows_are_updated_inside_the_loop(): void
    {
        Author::query()->chunkById(3, static function ($models): void {
            foreach ($models as $model) {
                $model->rating = 5;
                $model->save();
            }
        });

        $this->assertSame(7, $this->db()->table('authors')->where('rating', 5)->count());
    }

    public function test_each_lazy_lazy_by_id_and_cursor(): void
    {
        $names = [];
        Author::query()->orderBy('id')->each(static function ($model) use (&$names): void {
            $names[] = $model->name;
        }, 3);

        $this->assertSame($this->expected, $names);

        $lazy = Author::query()->orderBy('id')->lazy(3);

        $this->assertInstanceOf(LazyCollection::class, $lazy);
        $this->assertSame($this->expected, $lazy->map(static fn($model): string => $model->name)->all());
        $this->assertSame(
            $this->expected,
            Author::query()->lazyById(2)->map(static fn($model): string => $model->name)->all()
        );
        $this->assertSame(
            \array_reverse($this->expected),
            Author::query()->lazyByIdDesc(2)->map(static fn($model): string => $model->name)->all()
        );
        $this->assertSame(
            $this->expected,
            Author::query()->orderBy('id')->cursor()->map(static fn($model): string => $model->name)->all()
        );
        $this->assertSame(
            $this->expected,
            $this->db()->table('authors')->orderBy('id')->cursor()->map(static fn($row): string => $row->name)->all()
        );
    }
}
