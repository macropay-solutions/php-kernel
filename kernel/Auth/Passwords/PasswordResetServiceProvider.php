<?php

namespace MacropaySolutions\Kernel\Auth\Passwords;

use MacropaySolutions\Kernel\Contracts\Support\DeferrableProvider;
use MacropaySolutions\Kernel\Support\ServiceProvider;

class PasswordResetServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerPasswordBroker();
    }

    /**
     * Register the password broker instance.
     *
     * @return void
     */
    protected function registerPasswordBroker()
    {
        $this->app->singleton('auth.password', [self::class, 'getAuthPassword']);

        $this->app->bind('auth.password.broker', [self::class, 'getAuthPasswordBroker']);
    }

    public static function getAuthPassword($app): PasswordBrokerManager {
        return new PasswordBrokerManager($app);
    }

    public static function getAuthPasswordBroker($app) {
        return $app->make('auth.password')->broker();
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['auth.password', 'auth.password.broker'];
    }
}
