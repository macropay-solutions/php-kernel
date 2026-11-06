<?php

namespace MacropaySolutions\Kernel\Tests\Feature\Database;

use MacropaySolutions\Kernel\Database\Obvious\Model;
use MacropaySolutions\Kernel\Database\Schema\Blueprint;
use MacropaySolutions\Kernel\Tests\ApplicationTestCase;
use MacropaySolutions\Kernel\Tests\Fixtures\Database\Author;

/**
 * Obvious sets created_at and updated_at itself, so the columns need no database default.
 */
class ModelTimestampsTest extends ApplicationTestCase
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

    public function test_a_created_model_gets_both_timestamps(): void
    {
        $author = Author::query()->create(['name' => 'Ana']);

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $author->created_at);
        $this->assertSame($author->created_at, $author->updated_at);
        $this->assertSame($author->created_at, $this->db()->table('authors')->value('created_at'));
    }

    public function test_explicit_timestamps_are_kept(): void
    {
        $author = new Author(['name' => 'Ana']);
        $author->created_at = '2020-01-01 10:00:00';
        $author->save();

        $this->assertSame('2020-01-01 10:00:00', $this->db()->table('authors')->value('created_at'));
    }

    public function test_is_dirty_ignores_attributes_the_model_does_not_have(): void
    {
        $author = new Author(['name' => 'Ana']);

        $this->assertTrue($author->isDirty('name'));
        $this->assertFalse($author->isDirty('rating'));
        $this->assertSame(['name' => 'Ana'], $author->getDirty(['name', 'rating']));
    }
}
