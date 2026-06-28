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
        $this->registerSessionManager();

        $this->registerSessionDriver();

        $this->app->singleton(StartSession::class, function ($app) {
            return new StartSession($app->make(SessionManager::class), function () use ($app) {
                return $app->make(CacheFactory::class);
            });
        });
    }

    /**
     * Register the session manager instance.
     *
     * @return void
     */
    protected function registerSessionManager()
    {
        $this->app->singleton('session', function ($app) {
            return new SessionManager($app);
        });
    }

    /**
     * Register the session driver instance.
     *
     * @return void
     */
    protected function registerSessionDriver()
    {
        $this->app->singleton('session.store', function ($app) {
            // First, we will create the session manager which is responsible for the
            // creation of the various session drivers when they are needed by the
            // application instance, and will resolve them on a lazy load basis.
            return $app->make('session')->driver();
        });
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
            SessionManager::class,
            'session.store',
            \MacropaySolutions\Kernel\Contracts\Session\Session::class,
            \MacropaySolutions\Kernel\Session\Store::class,
            \MacropaySolutions\Kernel\Session\EncryptedStore::class,
        ];
    }
}
