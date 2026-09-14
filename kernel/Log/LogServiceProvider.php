<?php

namespace MacropaySolutions\Kernel\Log;

use MacropaySolutions\Kernel\Support\ServiceProvider;

class LogServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('log', [self::class, 'getLog']);
    }

    public static function getLog($app)
    {
        return new LogManager($app);
    }
}
