<?php

namespace MacropaySolutions\Kernel\Tests;

use MacropaySolutions\Kernel\Bus\Dispatcher;
use MacropaySolutions\Kernel\Cache\CacheManager;
use MacropaySolutions\Kernel\Config\Repository as Config;
use MacropaySolutions\Kernel\Container\Container;
use MacropaySolutions\Kernel\Contracts\Bus\Dispatcher as DispatcherContract;
use MacropaySolutions\Kernel\Contracts\Cache\Factory as CacheFactory;
use MacropaySolutions\Kernel\Contracts\Cache\Repository as CacheRepository;
use MacropaySolutions\Kernel\Contracts\Container\Container as ContainerContract;
use MacropaySolutions\Kernel\Contracts\Encryption\Encrypter as EncrypterContract;
use MacropaySolutions\Kernel\Contracts\Events\Dispatcher as EventsDispatcherContract;
use MacropaySolutions\Kernel\Encryption\Encrypter;
use MacropaySolutions\Kernel\Events\Dispatcher as EventsDispatcher;
use MacropaySolutions\Kernel\Tests\Fixtures\PayloadQueue;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Boots a container with the real cache manager (array stores), encrypter, event and bus dispatchers.
 * Dispatched jobs end up as payloads in $this->queue.
 */
abstract class TestCase extends BaseTestCase
{
    protected Container $container;

    protected CacheManager $cache;

    protected PayloadQueue $queue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = new Container();
        Container::setInstance($this->container);
        $this->container->instance(ContainerContract::class, $this->container);
        $this->container->instance(EventsDispatcherContract::class, new EventsDispatcher($this->container));

        $this->container->instance('config', new Config([
            'cache' => [
                'default' => 'array',
                'stores' => [
                    'array' => ['driver' => 'array'],
                    'locks' => ['driver' => 'array'],
                ],
            ],
        ]));

        $this->cache = new CacheManager($this->container);
        $this->container->instance('cache', $this->cache);
        $this->container->instance(CacheFactory::class, $this->cache);
        $this->container->instance(CacheRepository::class, $this->cache->store());
        $this->container->instance(EncrypterContract::class, new Encrypter(\random_bytes(32), 'aes-256-cbc'));

        $this->queue = new PayloadQueue();
        $this->queue->setContainer($this->container);
        $this->container->instance(
            DispatcherContract::class,
            new Dispatcher($this->container, fn(): PayloadQueue => $this->queue)
        );
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);

        parent::tearDown();
    }
}
