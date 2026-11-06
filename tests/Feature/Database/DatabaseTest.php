<?php

namespace MacropaySolutions\Kernel\Tests\Feature\Database;

use MacropaySolutions\Kernel\Database\Obvious\Model;
use MacropaySolutions\Kernel\Database\Obvious\ModelNotFoundException;
use MacropaySolutions\Kernel\Database\Schema\Blueprint;
use MacropaySolutions\Kernel\Tests\ApplicationTestCase;
use MacropaySolutions\Kernel\Tests\Fixtures\Database\Author;
use MacropaySolutions\Kernel\Tests\Fixtures\Database\Book;

/**
 * The query builder, transactions and Obvious models on an in-memory SQLite database.
 */
class DatabaseTest extends ApplicationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Model::unsetConnectionResolver();

        $schema = $this->db()->getSchemaBuilder();
        $schema->create('authors', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->integer('rating')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->nullable();
        });
        $schema->create('books', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('author_id')->constrained('authors');
            $table->string('title');
            $table->integer('pages');
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Model::unsetConnectionResolver();

        parent::tearDown();
    }

    public function test_the_query_builder_inserts_filters_orders_and_aggregates(): void
    {
        $this->seedAuthorsWithBooks();

        $titles = $this->db()->table('books')->where('pages', '>=', 100)->orderByDesc('pages')->pluck('title')->all();

        $this->assertSame(['Long', 'Medium'], $titles);
        $this->assertSame(3, $this->db()->table('books')->count());
        $this->assertEquals(450, $this->db()->table('books')->sum('pages'));
        $this->assertEquals(300, $this->db()->table('books')->max('pages'));
    }

    public function test_joins_and_grouping(): void
    {
        $this->seedAuthorsWithBooks();

        $rows = $this->db()->table('authors')
            ->join('books', 'books.author_id', '=', 'authors.id')
            ->groupBy('authors.name')
            ->orderBy('authors.name')
            ->selectRaw('authors.name, count(books.id) as total')
            ->get()
            ->map(fn(object $row): array => [$row->name, (int)$row->total])
            ->all();

        $this->assertSame([['Ana', 2], ['Ion', 1]], $rows);
    }

    public function test_update_and_delete_return_affected_rows(): void
    {
        $this->seedAuthorsWithBooks();

        $this->assertSame(2, $this->db()->table('books')->where('pages', '<', 200)->update(['pages' => 1]));
        $this->assertSame(2, $this->db()->table('books')->where('pages', 1)->delete());
        $this->assertSame(1, $this->db()->table('books')->count());
    }

    public function test_a_failed_transaction_is_rolled_back(): void
    {
        try {
            $this->db()->transaction(function (): void {
                $this->db()->table('authors')->insert(['name' => 'Rolled back']);

                throw new \RuntimeException('abort');
            });
        } catch (\RuntimeException) {
        }

        $this->assertSame(0, $this->db()->table('authors')->count());

        $this->db()->transaction(function (): void {
            $this->db()->table('authors')->insert(['name' => 'Committed']);
        });

        $this->assertSame(1, $this->db()->table('authors')->count());
    }

    public function test_nested_transactions_roll_back_to_the_savepoint(): void
    {
        $this->db()->transaction(function (): void {
            $this->db()->table('authors')->insert(['name' => 'Outer']);

            try {
                $this->db()->transaction(function (): void {
                    $this->db()->table('authors')->insert(['name' => 'Inner']);

                    throw new \RuntimeException('inner');
                });
            } catch (\RuntimeException) {
            }
        });

        $this->assertSame(['Outer'], $this->db()->table('authors')->pluck('name')->all());
    }

    public function test_foreign_keys_are_enforced(): void
    {
        $this->expectException(\MacropaySolutions\Kernel\Database\QueryException::class);

        $this->db()->table('books')->insert(['author_id' => 999, 'title' => 'Orphan', 'pages' => 1]);
    }

    public function test_models_are_created_found_updated_and_deleted(): void
    {
        $author = Author::query()->create(['name' => 'Ana', 'rating' => '5']);

        $this->assertTrue($author->exists);
        $this->assertSame(5, $author->rating);

        $found = Author::query()->findOrFail($author->id);
        $this->assertSame('Ana', $found->name);

        $found->update(['name' => 'Ana Maria']);
        $this->assertSame('Ana Maria', Author::query()->find($author->id)->name);

        $found->delete();
        $this->assertNull(Author::query()->find($author->id));
    }

    public function test_find_or_fail_throws_for_a_missing_model(): void
    {
        $this->expectException(ModelNotFoundException::class);

        Author::query()->findOrFail(404);
    }

    public function test_relations_and_eager_loading(): void
    {
        $this->seedAuthorsWithBooks();

        $ana = Author::query()->where('name', 'Ana')->firstOrFail();
        $this->assertSame(['Short', 'Long'], $ana->books()->orderBy('pages')->pluck('title')->all());

        $book = Book::query()->where('title', 'Medium')->firstOrFail();
        $this->assertSame('Ion', $book->author->name);

        $authors = Author::query()->with('books')->orderBy('name')->get();
        $this->assertTrue($authors[0]->relationLoaded('books'));
        $this->assertCount(2, $authors[0]->books);
        $this->assertCount(1, $authors[1]->books);
    }

    private function seedAuthorsWithBooks(): void
    {
        $ana = $this->db()->table('authors')->insertGetId(['name' => 'Ana']);
        $ion = $this->db()->table('authors')->insertGetId(['name' => 'Ion']);
        $this->db()->table('books')->insert([
            ['author_id' => $ana, 'title' => 'Short', 'pages' => 50],
            ['author_id' => $ana, 'title' => 'Long', 'pages' => 300],
            ['author_id' => $ion, 'title' => 'Medium', 'pages' => 100],
        ]);
    }
}
