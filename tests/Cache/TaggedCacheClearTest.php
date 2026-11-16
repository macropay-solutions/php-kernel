<?php

namespace MacropaySolutions\Kernel\Tests\Cache;

use MacropaySolutions\Framework\Application;
use MacropaySolutions\Kernel\Cache\ArrayStore;
use MacropaySolutions\Kernel\Container\Container;
use PHPUnit\Framework\TestCase;

/**
 * clear() on a tagged cache flushed the whole store instead of only the tagged items.
 */
class TaggedCacheClearTest extends TestCase
{
    private Container $previous;

    protected function setUp(): void
    {
        $this->previous = Container::getInstance();
        $application = (new \ReflectionClass(TaggedCacheClearTestApplication::class))->newInstanceWithoutConstructor();
        Container::setInstance($application);
    }

    protected function tearDown(): void
    {
        Container::setInstance($this->previous);
    }

    public function testClearOnlyRemovesTheTaggedItems(): void
    {
        $store = new ArrayStore();
        $store->put('untagged', 'keep', 60);
        $store->tags(['t'])->put('tagged', 'drop', 60);

        $this->assertTrue($store->tags(['t'])->clear());

        $this->assertSame('keep', $store->get('untagged'));
        $this->assertNull($store->tags(['t'])->get('tagged'));
    }
}

class TaggedCacheClearTestApplication extends Application
{
    public function environment(...$environments): mixed
    {
        return [] === $environments
            ? 'testing'
            : \in_array('testing', \is_array($environments[0]) ? $environments[0] : $environments, true);
    }
}
