<?php

namespace MacropaySolutions\Kernel\Tests\Feature\Cache;

use MacropaySolutions\Kernel\Cache\ArrayStore;
use MacropaySolutions\Kernel\Cache\Repository;
use MacropaySolutions\Kernel\Tests\TestCase;

class RepositoryNameTest extends TestCase
{
    public function test_resolved_stores_are_named(): void
    {
        $this->assertSame('array', $this->cache->store()->getName());
        $this->assertSame('locks', $this->cache->store('locks')->getName());
    }

    public function test_custom_creator_stores_are_named(): void
    {
        $this->container->make('config')->set('cache.stores.custom', ['driver' => 'custom']);
        $this->cache->extend('custom', fn(): Repository => new Repository(new ArrayStore()));

        $this->assertSame('custom', $this->cache->store('custom')->getName());
    }
}
