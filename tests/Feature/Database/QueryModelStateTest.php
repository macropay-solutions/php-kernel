<?php

namespace MacropaySolutions\Kernel\Tests\Feature\Database;

use MacropaySolutions\Kernel\Database\Obvious\Model;
use MacropaySolutions\Kernel\Database\Obvious\Relations\HasMany;
use MacropaySolutions\Kernel\Database\Schema\Blueprint;
use MacropaySolutions\Kernel\Tests\ApplicationTestCase;
use MacropaySolutions\Kernel\Tests\Fixtures\Database\Author;

/**
 * - withCasts() on a cloned query changed the casts of the original query.
 * - withCasts() on a relation did not reach create() / make().
 * - chunkById() / lazyById() paged from the accessor value of the key instead of the stored one.
 * - increment() / decrement() stored the extra attributes without their mutators.
 * - increment() / decrement() computed the model value from the column accessor and stored it through the
 *   column mutator, so the model no longer matched the database (or bcadd threw); a null column threw.
 */
class QueryModelStateTest extends ApplicationTestCase
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
    }

    protected function tearDown(): void
    {
        Model::unsetConnectionResolver();

        parent::tearDown();
    }

    public function test_with_casts_on_a_cloned_query_does_not_change_the_original_query(): void
    {
        Author::query()->create(['name' => 'Ana']);
        $query = Author::query();

        $cast = (clone $query)->withCasts(['name' => 'int'])->first();
        $original = $query->first();

        $this->assertSame(0, $cast->a->name);
        $this->assertSame('Ana', $original->a->name);
    }

    public function test_with_casts_on_a_relation_reaches_make_and_create(): void
    {
        $author = RatedByAuthor::query()->create(['name' => 'Ana']);

        $made = $author->r->ratedBy()->withCasts(['name' => 'int'])->make(['name' => '7']);
        $created = $author->r->ratedBy()->withCasts(['name' => 'int'])->create(['name' => '8']);

        $this->assertSame(7, $made->a->name);
        $this->assertSame(8, $created->a->name);
    }

    public function test_chunk_by_id_pages_from_the_stored_key(): void
    {
        foreach (['a', 'b', 'c'] as $name) {
            Author::query()->create(['name' => $name]);
        }

        $names = [];

        PrefixedKeyAuthor::query()->chunkById(2, function ($authors) use (&$names): void {
            foreach ($authors as $author) {
                $names[] = $author->a->name;
            }
        });

        $this->assertSame(['a', 'b', 'c'], $names);
        $this->assertSame(['a', 'b', 'c'], PrefixedKeyAuthor::query()->lazyById(2)->map(fn($a) => $a->a->name)->all());
    }

    public function test_increment_stores_mutated_extra_attributes(): void
    {
        $author = UppercaseNameAuthor::query()->create(['name' => 'ANA', 'rating' => 1]);

        $author->increment('rating', 1, ['name' => 'bob']);

        $this->assertSame('BOB', $this->db()->table('authors')->value('name'));
        $this->assertSame(2, (int)$this->db()->table('authors')->value('rating'));
    }

    public function test_increment_uses_the_raw_column_value(): void
    {
        $author = StarredRatingAuthor::query()->create(['name' => 'Ana']);
        $author->forceFill(['rating' => 5])->save();

        $author->increment('rating', 2);
        $author->decrement('rating');

        $this->assertSame(6, (int)$this->db()->table('authors')->value('rating'));
        $this->assertSame(6, (int)$author->getAttributes()['rating']);
        $this->assertSame('*6', $author->a->rating);
    }

    public function test_increment_of_a_null_column_does_not_throw(): void
    {
        $author = Author::query()->create(['name' => 'Ana']);

        $author->increment('rating', 1, ['name' => 'Bob']);

        $this->assertNull($author->getAttributes()['rating'] ?? null);
        $this->assertNull($this->db()->table('authors')->value('rating'));
        $this->assertSame('Bob', $this->db()->table('authors')->value('name'));
    }
}

class PrefixedKeyAuthor extends Author
{
    protected string $table = 'authors';

    protected static function segregatedAccessorsMap(): array
    {
        return [
            'id' => fn(mixed $value): ?string => $value !== null ? 'author-' . $value : null,
        ];
    }
}

class UppercaseNameAuthor extends Author
{
    protected string $table = 'authors';

    protected static function segregatedMutatorsMap(): array
    {
        return [
            'name' => function (?string $value): void {
                $this->attributes['name'] = $value !== null ? \strtoupper($value) : null;
            },
        ];
    }
}

class StarredRatingAuthor extends Author
{
    protected string $table = 'authors';

    protected static function segregatedAccessorsMap(): array
    {
        return [
            'rating' => fn(mixed $value): ?string => $value !== null ? '*' . $value : null,
        ];
    }

    protected static function segregatedMutatorsMap(): array
    {
        return [
            'rating' => function (mixed $value): void {
                $this->attributes['rating'] = $value !== null ? (string)\ltrim((string)$value, '*') : null;
            },
        ];
    }
}

class RatedByAuthor extends Author
{
    protected string $table = 'authors';

    protected static function segregatedRelationsDefinitionMap(): array
    {
        return [
            'ratedBy' => fn(): HasMany => $this->hasMany(Author::class, 'rating', 'id'),
        ];
    }
}
