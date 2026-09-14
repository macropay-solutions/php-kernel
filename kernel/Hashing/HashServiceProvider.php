<?php

namespace MacropaySolutions\Kernel\Hashing;

use MacropaySolutions\Kernel\Contracts\Support\DeferrableProvider;
use MacropaySolutions\Kernel\Support\ServiceProvider;

class HashServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('hash', [self::class, 'getHash']);

        $this->app->singleton('hash.driver', [self::class, 'getHashDriver']);
    }

    public static function getHash($app)
    {
        return new HashManager($app);
    }

    public static function getHashDriver($app)
    {
        return $app->make('hash')->driver();
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['hash', 'hash.driver'];
    }
}
