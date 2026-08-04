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
        $this->app->singleton(ChannelManager::class, fn($app) => new ChannelManager($app));

        $this->app->alias(
            ChannelManager::class,
            DispatcherContract::class
        );

        $this->app->alias(
            ChannelManager::class,
            FactoryContract::class
        );
    }
}
