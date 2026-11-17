<?php

namespace MacropaySolutions\Kernel\Tests\Cookie;

use MacropaySolutions\Kernel\Cache\CacheManager;
use MacropaySolutions\Kernel\Cookie\CookieJar;
use PHPUnit\Framework\TestCase;

class StaticCreatorAndNamedQueueArgumentsTest extends TestCase
{
    public function testAStaticClosureCanExtendAManager(): void
    {
        $manager = (new \ReflectionClass(CacheManager::class))->newInstanceWithoutConstructor();
        $manager->extend('static', static fn($app, $config) => 'store');
        $manager->extend('bound', function ($app, $config) {
            return $this::class;
        });

        $creators = (new \ReflectionProperty($manager, 'customCreators'))->getValue($manager);

        $this->assertSame('store', $creators['static'](null, []));
        $this->assertSame(CacheManager::class, $creators['bound'](null, []));
    }

    public function testQueueKeepsNamedArguments(): void
    {
        $jar = new CookieJar();
        $jar->queue(value: 'v', name: 'n');
        $jar->queue('n2', 'v2', path: '/p');

        $this->assertSame('v', $jar->queued('n')->getValue());
        $this->assertSame('v2', $jar->queued('n2', null, '/p')->getValue());
    }
}
