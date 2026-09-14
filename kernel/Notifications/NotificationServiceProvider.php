<?php

namespace MacropaySolutions\Kernel\Notifications;

use MacropaySolutions\Kernel\Contracts\Notifications\Dispatcher as DispatcherContract;
use MacropaySolutions\Kernel\Contracts\Notifications\Factory as FactoryContract;
use MacropaySolutions\Kernel\Support\ServiceProvider;

class NotificationServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(ChannelManager::class, [self::class, 'getChannelManager']);

        $this->app->alias(
            ChannelManager::class,
            DispatcherContract::class
        );

        $this->app->alias(
            ChannelManager::class,
            FactoryContract::class
        );
    }

    public static function getChannelManager($app)
    {
        return new ChannelManager($app);
    }
}
