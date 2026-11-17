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
        $manager->extend('staticMethod', StaticCreatorAndNamedQueueArgumentsTestFactory::make(...));
        $manager->extend('objectMethod', (new StaticCreatorAndNamedQueueArgumentsTestFactory())->create(...));
        $manager->extend('bound', function ($app, $config) {
            return $this::class;
        });

        $creators = (new \ReflectionProperty($manager, 'customCreators'))->getValue($manager);

        $this->assertSame('store', $creators['static'](null, []));
        $this->assertSame('static method', $creators['staticMethod'](null, []));
        $this->assertSame('object method', $creators['objectMethod'](null, []));
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

class StaticCreatorAndNamedQueueArgumentsTestFactory
{
    public static function make($app, array $config): string
    {
        return 'static method';
    }

    public function create($app, array $config): string
    {
        return 'object method';
    }
}
