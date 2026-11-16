<?php

namespace MacropaySolutions\Kernel\Tests\Database;

use MacropaySolutions\Kernel\Database\Obvious\Model;
use MacropaySolutions\Kernel\Database\Obvious\Relations\Pivot;
use MacropaySolutions\Kernel\Events\Dispatcher;
use PHPUnit\Framework\TestCase;

/**
 * - A pivot with only created_at as a pivot column enabled timestamps, so saving it also wrote a missing updated_at.
 * - A custom pivot with $timestamps = false had them turned on when the pivot columns included both timestamps.
 */
class PivotTimestampAttributesTest extends TestCase
{
    protected function setUp(): void
    {
        Model::setEventDispatcher(new Dispatcher());
    }

    protected function tearDown(): void
    {
        Model::unsetEventDispatcher();
    }

    public function testTimestampsNeedBothColumns(): void
    {
        $parent = new PivotTimestampAttributesTestModel();

        $this->assertFalse(Pivot::fromRawAttributes($parent, ['a_id' => 1, 'created_at' => 'x'], 'a_b')->timestamps);
        $this->assertTrue(
            Pivot::fromRawAttributes($parent, ['a_id' => 1, 'created_at' => 'x', 'updated_at' => 'y'], 'a_b')
                ->timestamps
        );
        $this->assertFalse(Pivot::fromRawAttributes($parent, ['a_id' => 1], 'a_b')->timestamps);
    }

    public function testACustomPivotWithoutTimestampsKeepsThemOff(): void
    {
        $parent = new PivotTimestampAttributesTestModel();
        $attributes = ['a_id' => 1, 'created_at' => 'x', 'updated_at' => 'y'];

        $custom = PivotTimestampAttributesTestPivot::class;

        $this->assertFalse($custom::fromRawAttributes($parent, $attributes, 'a_b')->timestamps);
        $this->assertFalse($custom::fromAttributes($parent, $attributes, 'a_b')->timestamps);
        $this->assertTrue(Pivot::fromAttributes($parent, $attributes, 'a_b')->timestamps);
    }
}

class PivotTimestampAttributesTestPivot extends Pivot
{
    public bool $timestamps = false;
}

class PivotTimestampAttributesTestModel extends Model
{
    protected string $table = 'a';
}
