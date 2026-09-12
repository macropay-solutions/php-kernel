<?php

namespace MacropaySolutions\Kernel\Pipeline;

use MacropaySolutions\Kernel\Contracts\Pipeline\Hub as PipelineHubContract;
use MacropaySolutions\Kernel\Contracts\Support\DeferrableProvider;
use MacropaySolutions\Kernel\Support\ServiceProvider;

class PipelineServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(
            PipelineHubContract::class,
            Hub::class
        );

        $this->app->bind('pipeline', [self::class, 'getPipeline']);
    }

    public static function getPipeline($app)
    {
        return new Pipeline($app);
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            PipelineHubContract::class,
            'pipeline',
        ];
    }
}
