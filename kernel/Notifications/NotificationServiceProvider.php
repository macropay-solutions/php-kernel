<?php

namespace MacropaySolutions\Kernel\Notifications;

use MacropaySolutions\Kernel\Contracts\Support\DeferrableProvider;
use MacropaySolutions\Kernel\Support\ServiceProvider;

class NotificationServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(ChannelManager::class, [self::class, 'getChannelManager']);
    }

    public static function getChannelManager($app)
    {
        return new ChannelManager($app);
    }

    public function provides()
    {
        return [
            ChannelManager::class,
        ];
    }
}
