<?php

namespace MacropaySolutions\Kernel\Tests\Database;

use MacropaySolutions\Kernel\Database\Obvious\Model;
use MacropaySolutions\Kernel\Database\Obvious\ModelNotFoundException;
use MacropaySolutions\Kernel\Database\Obvious\SoftDeletes;
use MacropaySolutions\Kernel\Events\Dispatcher;
use PHPUnit\Framework\TestCase;

class EnumIdsAndTrashedObserverTest extends TestCase
{
    protected function setUp(): void
    {
        Model::setEventDispatcher(new Dispatcher());
    }

    protected function tearDown(): void
    {
        Model::unsetEventDispatcher();
    }

    public function testEnumIdsAreScalarInTheMessage(): void
    {
        $e = (new ModelNotFoundException())->setModel(
            EnumIdsTestModel::class,
            [1, EnumIdsTestBackedId::Nine, EnumIdsTestPureId::Ten]
        );

        $this->assertSame([1, 9, 'Ten'], $e->getIds());
        $this->assertSame('No query results for model [' . EnumIdsTestModel::class . '] 1, 9, Ten', $e->getMessage());
    }

    public function testTrashedIsAnObservableEvent(): void
    {
        $this->assertTrue(\in_array('trashed', (new EnumIdsTestModel())->getObservableEvents(), true));
    }
}

enum EnumIdsTestBackedId: int
{
    case Nine = 9;
}

enum EnumIdsTestPureId
{
    case Ten;
}

class EnumIdsTestModel extends Model
{
    use SoftDeletes;

    protected string $table = 'items';
}
