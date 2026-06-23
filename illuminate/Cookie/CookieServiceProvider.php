<?php

namespace Illuminate\Cookie;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

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
            CookieJar::class,
            \Illuminate\Contracts\Cookie\Factory::class,
            \Illuminate\Contracts\Cookie\QueueingFactory::class,
        ];
    }
}
