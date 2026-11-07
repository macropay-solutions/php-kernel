<?php

namespace MacropaySolutions\Kernel\Tests\Feature\Database;

use MacropaySolutions\Kernel\Database\Obvious\Builder;
use MacropaySolutions\Kernel\Database\Obvious\InvalidCastException;
use MacropaySolutions\Kernel\Database\Obvious\Model;
use MacropaySolutions\Kernel\Database\Schema\Blueprint;
use MacropaySolutions\Kernel\Tests\ApplicationTestCase;
use MacropaySolutions\Kernel\Tests\Fixtures\Database\Article;
use MacropaySolutions\Kernel\Tests\Fixtures\Database\ArticleStatus;
use MacropaySolutions\Kernel\Tests\Fixtures\Database\Author;
use MacropaySolutions\Kernel\Tests\Fixtures\Database\BrokenArticle;

/**
 * The V1 ORM breaking changes: primitive-only casts and accessors, map-based scopes, builder extensions,
 * soft deletes on raw strings and no magic forwarding from the model.
 */
class StrictModelTest extends ApplicationTestCase
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
            $table->timestamps();
        });
        $schema->create('articles', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('author_id')->nullable();
            $table->string('title');
            $table->integer('views')->default(0);
            $table->string('status')->default('draft');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    protected function tearDown(): void
    {
        Model::unsetConnectionResolver();

        parent::tearDown();
    }

    public function test_int_string_and_backed_enum_casts(): void
    {
        $this->db()->table('articles')->insert([
            'author_id' => 7,
            'title' => 'Hello',
            'views' => '12',
            'status' => 'published',
            'created_at' => '2026-01-01 00:00:00',
        ]);

        $article = Article::query()->firstOrFail();

        $this->assertSame(12, $article->views);
        $this->assertSame('7', $article->author_id);
        $this->assertSame(ArticleStatus::Published, $article->status);
        $this->assertSame('published', $article->toArray()['status']);
    }

    public function test_an_enum_cast_stores_the_backing_value(): void
    {
        $article = Article::query()->create(['title' => 'Hello', 'status' => ArticleStatus::Published]);

        $this->assertSame('published', $article->getAttributes()['status']);
        $this->assertSame('published', $this->db()->table('articles')->value('status'));

        $article->status = 'draft';

        $this->assertSame(ArticleStatus::Draft, $article->status);
    }

    public function test_an_invalid_enum_value_is_rejected_on_assignment(): void
    {
        $this->expectException(\ValueError::class);

        new Article(['title' => 'Hello', 'status' => 'archived']);
    }

    public function test_casts_other_than_int_string_and_backed_enum_throw_on_read(): void
    {
        $this->db()->table('articles')->insert([
            'title' => 'Hello',
            'views' => 1,
            'created_at' => '2026-01-01 00:00:00',
        ]);

        $article = BrokenArticle::query()->firstOrFail();

        try {
            $article->created_at;
            $this->fail('A datetime cast must throw.');
        } catch (InvalidCastException $e) {
            $this->assertStringContainsString('created_at', $e->getMessage());
        }

        $this->expectException(InvalidCastException::class);

        $article->views;
    }

    public function test_timestamps_are_raw_strings_not_carbon(): void
    {
        $article = Article::query()->create(['title' => 'Hello']);

        $this->assertIsString($article->created_at);
        $this->assertIsString(Article::query()->firstOrFail()->created_at);
    }

    public function test_an_accessor_returning_an_object_is_rejected(): void
    {
        $article = new BrokenArticle();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('must return int, string, \BackedEnum, or null');

        $article->meta;
    }

    public function test_a_mutator_storing_an_object_is_rejected(): void
    {
        $article = new BrokenArticle();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Mutators must strictly store primitives');

        $article->title = 'Hello';
    }

    public function test_segregated_accessors_and_mutators_run(): void
    {
        $article = new Article(['title' => '  Hello  ']);

        $this->assertSame('Hello', $article->title);
        $this->assertSame('HELLO', $article->headline);
    }

    public function test_named_scopes_come_from_the_scope_map_and_are_called_via_scopes(): void
    {
        $this->seedArticles();

        $this->assertSame(
            ['Big', 'Huge'],
            Article::query()->scopes('popular')->orderBy('views')->pluck('title')->all()
        );
        $this->assertSame(['Huge'], Article::query()->scopes(['minViews' => 500])->pluck('title')->all());
        $this->assertSame(
            ['Huge'],
            Article::query()->scopes(['popular', 'minViews' => [500]])->pluck('title')->all()
        );
    }

    public function test_scopes_are_not_reachable_as_builder_methods(): void
    {
        $this->expectException(\BadMethodCallException::class);

        Article::query()->popular();
    }

    public function test_builder_extensions_receive_the_builder_and_their_arguments(): void
    {
        $this->seedArticles();
        $query = Article::query();
        $query->addExtension(
            'titled',
            static fn(Builder $builder, string $title): Builder => $builder->where('title', $title)
        );

        $this->assertTrue($query->hasExtension('titled'));
        $this->assertFalse($query->hasExtension('missing'));
        $this->assertSame(['Big'], $query->titled('Big')->pluck('title')->all());
    }

    public function test_getting_a_missing_extension_throws(): void
    {
        $this->expectException(\TypeError::class);

        Article::query()->getExtension('missing');
    }

    public function test_soft_delete_sets_deleted_at_and_updated_at_to_the_same_raw_string(): void
    {
        $article = Article::query()->create(['title' => 'Hello']);

        $article->delete();

        $row = $this->db()->table('articles')->first();
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $row->deleted_at);
        $this->assertSame($row->deleted_at, $row->updated_at);
        $this->assertTrue($article->trashed());
        $this->assertFalse($article->isDirty());
    }

    public function test_soft_delete_extensions_filter_and_restore(): void
    {
        $this->seedArticles();
        Article::query()->where('title', 'Big')->firstOrFail()->delete();

        $this->assertSame(['Huge', 'Small'], Article::query()->orderBy('title')->pluck('title')->all());
        $this->assertSame(
            ['Big', 'Huge', 'Small'],
            Article::query()->withTrashed()->orderBy('title')->pluck('title')->all()
        );
        $this->assertSame(['Big'], Article::query()->onlyTrashed()->pluck('title')->all());

        $this->assertSame(1, Article::query()->onlyTrashed()->restore());
        $this->assertSame(3, Article::query()->count());
    }

    public function test_force_delete_removes_the_row(): void
    {
        $article = Article::query()->create(['title' => 'Hello']);

        $article->forceDelete();

        $this->assertSame(0, $this->db()->table('articles')->count());
    }

    public function test_an_empty_updated_at_saves_without_touching_it(): void
    {
        $this->db()->table('articles')->insert([
            'title' => 'Hello',
            'created_at' => '2020-01-01 00:00:00',
            'updated_at' => '2020-01-01 00:00:00',
        ]);
        $article = Article::query()->firstOrFail();

        $article->title = 'Changed';
        $article->updated_at = '';
        $article->save();

        $row = $this->db()->table('articles')->first();
        $this->assertSame('Changed', $row->title);
        $this->assertSame('2020-01-01 00:00:00', $row->updated_at);
    }

    public function test_an_update_refreshes_updated_at_but_keeps_created_at(): void
    {
        $this->db()->table('articles')->insert([
            'title' => 'Hello',
            'created_at' => '2020-01-01 00:00:00',
            'updated_at' => '2020-01-01 00:00:00',
        ]);
        $article = Article::query()->firstOrFail();

        $article->title = 'Changed';
        $article->save();

        $row = $this->db()->table('articles')->first();
        $this->assertSame('2020-01-01 00:00:00', $row->created_at);
        $this->assertNotSame('2020-01-01 00:00:00', $row->updated_at);
    }

    public function test_timestamp_and_soft_delete_columns_are_datetime(): void
    {
        $blueprint = new Blueprint('posts', function (Blueprint $table): void {
            $table->timestamps();
            $table->softDeletes();
        });

        $columns = [];

        foreach ($blueprint->getColumns() as $column) {
            $columns[$column->name] = [$column->type, (bool)$column->nullable];
        }

        $this->assertSame(
            [
                'created_at' => ['dateTime', false],
                'updated_at' => ['dateTime', true],
                'deleted_at' => ['dateTime', true],
            ],
            $columns
        );
    }

    public function test_static_builder_calls_on_the_model_are_rejected(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Use ::query()->where() instead');

        Article::where('title', 'Hello');
    }

    public function test_builder_calls_on_a_model_instance_are_rejected(): void
    {
        $this->expectException(\BadMethodCallException::class);

        (new Article())->where('title', 'Hello');
    }

    public function test_dynamic_where_methods_are_gone(): void
    {
        $this->expectException(\BadMethodCallException::class);

        Article::query()->whereTitle('Hello')->get();
    }

    public function test_where_belongs_to_is_gone(): void
    {
        $this->assertFalse(\method_exists(Builder::class, 'whereBelongsTo'));
        $this->assertFalse(\method_exists(Builder::class, 'orWhereBelongsTo'));
    }

    public function test_relations_are_reached_through_r_or_call_segregated_relation(): void
    {
        $authorId = $this->db()->table('authors')->insertGetId([
            'name' => 'Ana',
            'created_at' => '2026-01-01 00:00:00',
        ]);
        Article::query()->create(['title' => 'Hello', 'author_id' => $authorId]);
        $article = Article::query()->firstOrFail();

        $this->assertSame('Ana', $article->r->writtenBy()->firstOrFail()->name);
        $this->assertSame('Ana', $article->callSegregatedRelation('writtenBy')->firstOrFail()->name);
        $this->assertFalse(isset($article->r->writtenBy));
        $this->assertSame('Ana', $article->r->writtenBy->name);
        $this->assertTrue($article->relationLoaded('writtenBy'));
        $this->assertTrue(isset($article->r->writtenBy));

        unset($article->r->writtenBy);

        $this->assertFalse($article->relationLoaded('writtenBy'));
    }

    public function test_camel_case_relation_names_are_kept_in_to_array(): void
    {
        $authorId = $this->db()->table('authors')->insertGetId([
            'name' => 'Ana',
            'created_at' => '2026-01-01 00:00:00',
        ]);
        Article::query()->create(['title' => 'Hello', 'author_id' => $authorId]);

        $array = Article::query()->with('writtenBy')->firstOrFail()->toArray();

        $this->assertArrayHasKey('writtenBy', $array);
        $this->assertArrayNotHasKey('written_by', $array);
        $this->assertSame('Ana', $array['writtenBy']['name']);
    }

    public function test_the_a_proxy_reads_and_writes_attributes(): void
    {
        $article = new Article();

        $article->a->title = ' Hello ';

        $this->assertSame('Hello', $article->a->title);
        $this->assertTrue(isset($article->a->title));

        unset($article->a->title);

        $this->assertFalse(isset($article->a->title));
    }

    public function test_an_incrementing_model_casts_its_key_to_int(): void
    {
        $id = Author::query()->create(['name' => 'Ana'])->getKey();

        $this->assertIsInt($id);
        $this->assertIsInt(Author::query()->firstOrFail()->id);
    }

    private function seedArticles(): void
    {
        $this->db()->table('articles')->insert([
            ['title' => 'Small', 'views' => 5, 'created_at' => '2026-01-01 00:00:00'],
            ['title' => 'Big', 'views' => 150, 'created_at' => '2026-01-01 00:00:00'],
            ['title' => 'Huge', 'views' => 900, 'created_at' => '2026-01-01 00:00:00'],
        ]);
    }
}
