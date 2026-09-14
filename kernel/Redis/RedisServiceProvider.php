<?php

namespace MacropaySolutions\Kernel\Redis;

use MacropaySolutions\Kernel\Contracts\Support\DeferrableProvider;
use MacropaySolutions\Kernel\Support\Arr;
use MacropaySolutions\Kernel\Support\ServiceProvider;

class RedisServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('redis', [self::class, 'getRedis']);

        $this->app->bind('redis.connection', [self::class, 'getRedisConnection']);
    }

    public static function getRedis($app)
    {
        $config = $app->make('config')->get('database.redis', []);

        return new RedisManager($app, Arr::pull($config, 'client', 'phpredis'), $config);
    }

    public static function getRedisConnection($app)
    {
        return $app->make('redis')->connection();
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['redis', 'redis.connection'];
    }
}
