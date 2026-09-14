<?php

namespace MacropaySolutions\Kernel\Broadcasting;

use MacropaySolutions\Kernel\Contracts\Broadcasting\Broadcaster as BroadcasterContract;
use MacropaySolutions\Kernel\Contracts\Broadcasting\Factory as BroadcastingFactory;
use MacropaySolutions\Kernel\Contracts\Support\DeferrableProvider;
use MacropaySolutions\Kernel\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(BroadcastManager::class, [self::class, 'getBroadcastManager']);

        $this->app->singleton(BroadcasterContract::class, [self::class, 'getBroadcastManagerConnection']);

        $this->app->alias(BroadcastManager::class, BroadcastingFactory::class);
    }

    public static function getBroadcastManager($app)
    {
        return new BroadcastManager($app);
    }

    public static function getBroadcastManagerConnection($app)
    {
        return $app->make(BroadcastManager::class)->connection();
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            BroadcastManager::class,
            BroadcastingFactory::class,
            BroadcasterContract::class,
        ];
    }
}
