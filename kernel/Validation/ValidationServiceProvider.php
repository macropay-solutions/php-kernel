<?php

namespace MacropaySolutions\Kernel\Validation;

use MacropaySolutions\Kernel\Contracts\Support\DeferrableProvider;
use MacropaySolutions\Kernel\Contracts\Validation\UncompromisedVerifier;
use MacropaySolutions\Kernel\Support\ServiceProvider;

class ValidationServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('validation.presence', [self::class, 'getValidationPresence']);

        $this->app->singleton(UncompromisedVerifier::class, [self::class, 'getUncompromisedVerifier']);

        $this->app->singleton('validator', [self::class, 'getValidator']);
    }

    public static function getValidationPresence($app)
    {
        return new DatabasePresenceVerifier($app->make('db'));
    }

    public static function getUncompromisedVerifier($app)
    {
        return new NotPwnedVerifier($app->make(\GuzzleHttp\Client::class));
    }

    public static function getValidator($app)
    {
        $validator = new Factory($app->make('translator'), $app);

        if ($app->bound('db') && $app->bound('validation.presence')) {
            $validator->setPresenceVerifier($app->make('validation.presence'));
        }

        return $validator;
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['validator', 'validation.presence', UncompromisedVerifier::class];
    }
}
