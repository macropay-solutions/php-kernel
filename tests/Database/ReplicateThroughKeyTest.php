<?php

namespace MacropaySolutions\Kernel\Tests\Database;

use MacropaySolutions\Kernel\Database\Obvious\Model;
use MacropaySolutions\Kernel\Events\Dispatcher;
use PHPUnit\Framework\TestCase;

/**
 * A model loaded through a has-many-through relation kept the selected framework_through_key in replicate(), so
 * saving the copy inserted a column that does not exist.
 */
class ReplicateThroughKeyTest extends TestCase
{
    protected function setUp(): void
    {
        Model::setEventDispatcher(new Dispatcher());
    }

    protected function tearDown(): void
    {
        Model::unsetEventDispatcher();
    }

    public function testTheThroughKeyIsNotReplicated(): void
    {
        $model = new ReplicateThroughKeyTestModel();
        $model->setRawAttributes(['id' => 1, 'name' => 'a', 'framework_through_key' => 7], true);

        $this->assertSame(['name' => 'a'], $model->replicate()->getAttributes());
    }
}

class ReplicateThroughKeyTestModel extends Model
{
    protected string $table = 'posts';

    public bool $timestamps = false;
}
