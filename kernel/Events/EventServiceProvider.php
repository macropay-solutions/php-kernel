<?php

namespace MacropaySolutions\Kernel\Events;

use MacropaySolutions\Kernel\Contracts\Queue\Factory as QueueFactoryContract;
use MacropaySolutions\Kernel\Support\ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('events', function ($app) {
//            return (new Dispatcher($app))->setQueueResolver(function () use ($app) {
            return $app->makeWithoutAlias(Dispatcher::class, [$app])->setQueueResolver(function () use ($app) {
                return $app->make(QueueFactoryContract::class);
            })->setTransactionManagerResolver(function () use ($app) {
                return $app->bound('db.transactions')
                    ? $app->make('db.transactions')
                    : null;
            });
        });
    }
}
