<?php

namespace MacropaySolutions\Kernel\Tests\Feature\Database;

use MacropaySolutions\Kernel\Database\Obvious\Model;
use MacropaySolutions\Kernel\Database\Schema\Blueprint;
use MacropaySolutions\Kernel\Tests\ApplicationTestCase;
use MacropaySolutions\Kernel\Tests\Fixtures\Database\Author;

/**
 * wasChanged() / getChanges() describe the last save: a save without changes reported the previous save's changes.
 */
class ModelWasChangedTest extends ApplicationTestCase
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

    public function test_changes_of_the_last_save_are_reported(): void
    {
        $author = Author::query()->create(['name' => 'Ana']);
        $author->name = 'Bob';
        $author->save();

        $this->assertTrue($author->wasChanged('name'));
        $this->assertSame('Bob', $author->getChanges()['name'] ?? null);
    }

    public function test_a_save_without_changes_clears_the_previous_changes(): void
    {
        $author = Author::query()->create(['name' => 'Ana']);
        $author->name = 'Bob';
        $author->save();

        $this->assertTrue($author->save());

        $this->assertFalse($author->wasChanged());
        $this->assertSame([], $author->getChanges());
    }
}
