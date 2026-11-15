<?php

namespace MacropaySolutions\Kernel\Tests\Cache;

use MacropaySolutions\Kernel\Cache\ArrayStore;
use MacropaySolutions\Kernel\Cache\Repository;
use MacropaySolutions\Kernel\Support\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * touch() with a negative or past TTL passed 0 on to the store, which reads it as "forever"; it now removes the item
 * (through its item key, like a zero TTL).
 */
class RepositoryTouchTest extends TestCase
{
    public function testZeroAndNegativeTtlRemoveTheItem(): void
    {
        $cache = new Repository(new ArrayStore());

        $cache->put('a', 'value', 60);
        $cache->put('b', 'value', 60);

        $this->assertTrue($cache->touch('a', 0));
        $this->assertTrue($cache->touch('b', -5));

        $this->assertNull($cache->get('a'));
        $this->assertNull($cache->get('b'));
    }

    public function testNullTtlKeepsTheItem(): void
    {
        $cache = new Repository(new ArrayStore());
        $cache->put('a', 'value', 60);

        $this->assertTrue($cache->touch('a', null));
        $this->assertSame('value', $cache->get('a'));
    }

    public function testDateTimeTtlWithoutDeprecations(): void
    {
        $cache = new Repository(new ArrayStore());
        $cache->put('a', 'value', new \DateTimeImmutable('+1 hour'));
        $cache->put('b', 'value', 60);

        $this->assertSame('value', $cache->get('a'));
        $this->assertTrue($cache->touch('b', new \DateTimeImmutable('-1 hour')));
        $this->assertNull($cache->get('b'));
    }

    public function testDateIntervalTtlFollowsTheFrozenTime(): void
    {
        Carbon::setTestNow(Carbon::create(2020, 1, 1));

        try {
            $cache = new Repository(new ArrayStore());
            $cache->put('a', 'value', 60);

            $this->assertTrue($cache->touch('a', new \DateInterval('PT1H')));
            $this->assertSame('value', $cache->get('a'));
        } finally {
            Carbon::setTestNow();
        }
    }
}
