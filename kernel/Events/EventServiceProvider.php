<?php

namespace MacropaySolutions\Kernel\Events;

use MacropaySolutions\Kernel\Contracts\Queue\Factory as QueueFactoryContract;
use MacropaySolutions\Kernel\Contracts\Support\DeferrableProvider;
use MacropaySolutions\Kernel\Support\ServiceProvider;

class EventServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('events', [self::class, 'getEvents']);
    }

    public static function getEvents($app)
    {
//        return (new Dispatcher($app))->setQueueResolver(function () use ($app) {
        return $app->makeWithoutAlias(Dispatcher::class, [$app])
            ->setQueueResolver(static function () use ($app) {
                return $app->make(QueueFactoryContract::class);
            })
            ->setTransactionManagerResolver(static function () use ($app) {
                return $app->bound('db.transactions')
                    ? $app->make('db.transactions')
                    : null;
            });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            'events',
        ];
    }
}
