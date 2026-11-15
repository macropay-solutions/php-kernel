<?php

namespace MacropaySolutions\Kernel\Tests\Cache;

use MacropaySolutions\Framework\Application;
use MacropaySolutions\Kernel\Cache\ArrayStore;
use MacropaySolutions\Kernel\Container\Container;
use PHPUnit\Framework\TestCase;

/**
 * touch() on a tagged cache was not capped: a null or long ttl let a tagged item outlive its tag version keys,
 * so it could be served again after a flush once the version restarted from 1.
 */
class TaggedCacheTouchTest extends TestCase
{
    private Container $previous;

    protected function setUp(): void
    {
        $this->previous = Container::getInstance();
        $application = (new \ReflectionClass(TaggedCacheTouchTestApplication::class))->newInstanceWithoutConstructor();
        Container::setInstance($application);
    }

    protected function tearDown(): void
    {
        Container::setInstance($this->previous);
    }

    public function testNullTtlIsRejected(): void
    {
        $cache = (new ArrayStore())->tags(['t']);
        $cache->put('k', 'v', 60);

        $this->expectException(\InvalidArgumentException::class);

        $cache->touch('k');
    }

    public function testTtlOverTheCapIsRejected(): void
    {
        $cache = (new ArrayStore())->tags(['t']);
        $cache->put('k', 'v', 60);

        $this->expectException(\InvalidArgumentException::class);

        $cache->touch('k', Container::TAGGED_CACHE_TTL_CAP_SECONDS + 1);
    }

    public function testTtlWithinTheCapExtendsAndZeroRemoves(): void
    {
        $cache = (new ArrayStore())->tags(['t']);
        $cache->put('k', 'v', 60);

        $this->assertTrue($cache->touch('k', 600));
        $this->assertSame('v', $cache->get('k'));

        $this->assertTrue($cache->touch('k', 0));
        $this->assertNull($cache->get('k'));
    }
}

class TaggedCacheTouchTestApplication extends Application
{
    public function environment(...$environments): mixed
    {
        return [] === $environments
            ? 'testing'
            : \in_array('testing', \is_array($environments[0]) ? $environments[0] : $environments, true);
    }
}
