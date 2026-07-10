<?php

namespace MacropaySolutions\Kernel\Cookie;

use MacropaySolutions\Kernel\Contracts\Support\DeferrableProvider;
use MacropaySolutions\Kernel\Support\ServiceProvider;

class CookieServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('cookie', function ($app) {
            $config = $app->make('config')->get('session');

            return (new CookieJar())->setDefaultPathAndDomain(
                $config['path'],
                $config['domain'],
                $config['secure'],
                $config['same_site'] ?? null
            );
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
            'cookie',
        ];
    }
}
