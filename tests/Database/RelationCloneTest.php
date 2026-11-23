<?php

namespace MacropaySolutions\Kernel\Tests\Database;

use MacropaySolutions\Kernel\Database\ConnectionResolver;
use MacropaySolutions\Kernel\Database\Obvious\Builder;
use MacropaySolutions\Kernel\Database\Obvious\Model;
use MacropaySolutions\Kernel\Database\Obvious\Relations\HasMany;
use MacropaySolutions\Kernel\Database\SQLiteConnection;
use MacropaySolutions\Kernel\Events\Dispatcher;
use PDO;
use PHPUnit\Framework\TestCase;

class RelationCloneTest extends TestCase
{
    protected function setUp(): void
    {
        $resolver = new ConnectionResolver([
            'sqlite' => new SQLiteConnection(new PDO('sqlite::memory:'), config: ['name' => 'sqlite']),
        ]);
        $resolver->setDefaultConnection('sqlite');
        Model::setConnectionResolver($resolver);
        Model::setEventDispatcher(new Dispatcher());
    }

    protected function tearDown(): void
    {
        Model::unsetEventDispatcher();
        Model::unsetConnectionResolver();
    }

    public function testCloneRelationReturnsAnIndependentCopyOfTheRelation(): void
    {
        $relation = (new RelationCloneUser())->posts();
        $copy = $relation->cloneRelation();
        $copy->where('title', 'a');

        $this->assertInstanceOf(HasMany::class, $copy);
        $this->assertNotSame($relation, $copy);
        $this->assertNotSame($relation->getQuery(), $copy->getQuery());
        $this->assertSame($copy->getQuery()->getModel(), $copy->getRelated());
        // the where added to the copy does not reach the original
        $this->assertCount(\count($relation->getBaseQuery()->wheres) + 1, $copy->getBaseQuery()->wheres);
    }

    public function testCloneIsStillForwardedToTheBuilder(): void
    {
        $relation = (new RelationCloneUser())->posts();
        $builder = $relation->clone();

        $this->assertInstanceOf(Builder::class, $builder);
        $this->assertNotSame($relation->getQuery(), $builder);
        $this->assertSame($relation->getBaseQuery()->wheres, $builder->getQuery()->wheres);
    }
}

class RelationCloneUser extends Model
{
    public function posts(): HasMany
    {
        return $this->hasMany(RelationClonePost::class, 'user_id', 'id');
    }
}

class RelationClonePost extends Model
{
}
