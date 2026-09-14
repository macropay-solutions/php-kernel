<?php

namespace MacropaySolutions\Kernel\Session;

use MacropaySolutions\Kernel\Contracts\Cache\Factory as CacheFactory;
use MacropaySolutions\Kernel\Contracts\Support\DeferrableProvider;
use MacropaySolutions\Kernel\Session\Middleware\StartSession;
use MacropaySolutions\Kernel\Support\ServiceProvider;

class SessionServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('session', [self::class, 'getSessionManager']);

        $this->app->singleton('session.store', [self::class, 'getSessionStore']);

        $this->app->singleton(StartSession::class, [self::class, 'getStartSession']);
    }

    public static function getSessionManager($app)
    {
        return new SessionManager($app);
    }

    public static function getSessionStore($app)
    {
        return $app->make('session')->driver();
    }

    public static function getStartSession($app)
    {
        return new StartSession($app->make(SessionManager::class), [self::class, 'getCacheFactory']);
    }

    public static function getCacheFactory()
    {
        return \app()->make(CacheFactory::class);
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            StartSession::class,
            'session',
            'session.store',
        ];
    }
}
