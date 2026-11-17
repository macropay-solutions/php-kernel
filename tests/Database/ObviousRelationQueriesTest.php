<?php

namespace MacropaySolutions\Kernel\Tests\Database;

use MacropaySolutions\Kernel\Database\ConnectionResolver;
use MacropaySolutions\Kernel\Database\Obvious\Model;
use MacropaySolutions\Kernel\Database\Obvious\Relations\HasMany;
use MacropaySolutions\Kernel\Database\Obvious\Relations\HasManyThrough;
use MacropaySolutions\Kernel\Database\Obvious\Relations\HasOne;
use MacropaySolutions\Kernel\Database\Obvious\SoftDeletes;
use MacropaySolutions\Kernel\Database\Query\Grammars\SqlServerGrammar;
use MacropaySolutions\Kernel\Database\SQLiteConnection;
use MacropaySolutions\Kernel\Events\Dispatcher;
use PDO;
use PHPUnit\Framework\TestCase;

class ObviousRelationQueriesTest extends TestCase
{
    private SQLiteConnection $connection;

    protected function setUp(): void
    {
        $this->connection = new SQLiteConnection(new PDO('sqlite::memory:'), config: ['name' => 'sqlite']);
        $resolver = new ConnectionResolver(['sqlite' => $this->connection]);
        $resolver->setDefaultConnection('sqlite');
        Model::setConnectionResolver($resolver);
        Model::setEventDispatcher(new Dispatcher());

        $this->connection->statement('create table nodes (id integer primary key, parent_id int null, title text null,'
            . ' deleted_at text null, created_at text null, updated_at text null)');
        $this->connection->statement('create table countries (id integer primary key)');
        $this->connection->statement('create table users (id integer primary key, country_id int)');
        $this->connection->table('nodes')->insert([
            ['id' => 1, 'parent_id' => null, 'title' => 'a'],
            ['id' => 2, 'parent_id' => 1, 'title' => 'b'],
            ['id' => 3, 'parent_id' => 1, 'title' => 'c'],
        ]);
        $this->connection->table('countries')->insert(['id' => 1]);
        $this->connection->table('users')->insert(['id' => 1, 'country_id' => 1]);
    }

    protected function tearDown(): void
    {
        Model::unsetEventDispatcher();
        Model::unsetConnectionResolver();
    }

    public function testWhereNotWithAnArrayIsNegatedOnce(): void
    {
        $ids = ObviousRelationQueriesNode::query()->whereNot(['title' => 'a', 'id' => 1])->orderBy('id')->pluck('id');

        $this->assertSame([2, 3], $ids->all());
    }

    public function testWithAcceptsAnExplicitNullCallback(): void
    {
        $this->assertCount(3, ObviousRelationQueriesNode::query()->with('children', null)->get());
    }

    public function testHasOnASelfReferencingOneOfManyRelation(): void
    {
        $this->assertSame([1], ObviousRelationQueriesNode::query()->has('latestChild')->pluck('id')->all());
        $this->assertSame(
            [1 => 3, 2 => null, 3 => null],
            ObviousRelationQueriesNode::query()->withMax('latestChild', 'id')->pluck('latest_child_max_id', 'id')->all()
        );
    }

    public function testRestoreOrCreateOnAHasManySetsTheForeignKey(): void
    {
        $node = ObviousRelationQueriesNode::query()->find(1);
        $child = $node->r->children()->restoreOrCreate(['title' => 'new']);

        $this->assertSame(1, $child->getAttributeValue('parent_id'));
    }

    public function testFindOrNewThroughKeepsTheRelatedKey(): void
    {
        $country = ObviousRelationQueriesCountry::query()->find(1);

        $this->assertSame(2, $country->r->nodes()->findOrNew(2)->getKey());
    }

    public function testSqlServerSelectsExistsAsACaseExpression(): void
    {
        $grammar = (new \ReflectionClass(SqlServerGrammar::class))->newInstanceWithoutConstructor();

        $this->assertSame('case when exists(select 1) then 1 else 0 end', $grammar->compileExistsColumn('select 1'));
    }
}

class ObviousRelationQueriesNode extends Model
{
    use SoftDeletes;

    protected string $table = 'nodes';

    protected array $guarded = [];

    protected static function segregatedRelationsDefinitionMap(): array
    {
        return [
            'children' => fn(): HasMany => $this->hasMany(ObviousRelationQueriesNode::class, 'parent_id', 'id'),
            'latestChild' => fn(): HasOne => $this->hasOne(ObviousRelationQueriesNode::class, 'parent_id', 'id')
                ->latestOfMany(),
        ];
    }
}

class ObviousRelationQueriesCountry extends Model
{
    protected string $table = 'countries';

    protected static function segregatedRelationsDefinitionMap(): array
    {
        return [
            'nodes' => fn(): HasManyThrough => $this->hasManyThrough(
                ObviousRelationQueriesNode::class,
                ObviousRelationQueriesUser::class,
                'country_id',
                'parent_id',
                'id',
                'id'
            ),
        ];
    }
}

class ObviousRelationQueriesUser extends Model
{
    protected string $table = 'users';
}
