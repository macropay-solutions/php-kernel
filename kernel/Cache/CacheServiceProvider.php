<?php

namespace MacropaySolutions\Kernel\Cache;

use MacropaySolutions\Kernel\Contracts\Support\DeferrableProvider;
use MacropaySolutions\Kernel\Support\ServiceProvider;
use Symfony\Component\Cache\Adapter\Psr16Adapter;

class CacheServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('cache', [self::class, 'getCacheManager']);

        $this->app->singleton('cache.store', [self::class, 'getCacheStore']);

        $this->app->singleton('cache.psr6', [self::class, 'getCachePsr6']);

        $this->app->singleton('memcached.connector', [self::class, 'getMemcachedConnector']);

        $this->app->singleton(RateLimiter::class, [self::class, 'getRateLimiter']);
    }

    public static function getCacheManager($app)
    {
        return new CacheManager($app);
    }

    public static function getCacheStore($app)
    {
        return $app->make('cache')->driver();
    }

    public static function getCachePsr6($app)
    {
        return new Psr16Adapter($app->make('cache.store'));
    }

    public static function getMemcachedConnector()
    {
        return new MemcachedConnector();
    }

    public static function getRateLimiter($app)
    {
        return new RateLimiter(
            $app->make('cache')->driver(
                $app->make('config')->get('cache.limiter')
            )
        );
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            'cache',
            'cache.store',
            'cache.psr6',
            'memcached.connector',
            RateLimiter::class,
        ];
    }
}
