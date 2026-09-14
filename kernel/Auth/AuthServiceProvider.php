<?php

namespace MacropaySolutions\Kernel\Auth;

use MacropaySolutions\Kernel\Contracts\Auth\Authenticatable as AuthenticatableContract;
use MacropaySolutions\Kernel\Contracts\Support\DeferrableProvider;
use MacropaySolutions\Kernel\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerAuthenticator();
        $this->registerUserResolver();
        $this->registerRequestRebindHandler();
        $this->registerEventRebindHandler();
    }

    /**
     * Register the authenticator services.
     *
     * @return void
     */
    protected function registerAuthenticator()
    {
        $this->app->singleton('auth', [self::class, 'getAuthManager']);

        $this->app->singleton('auth.driver', [self::class, 'getAuthDriver']);
    }

    public static function getAuthManager($app)
    {
        return new AuthManager($app);
    }

    public static function getAuthDriver($app)
    {
        return $app->make('auth')->guard();
    }

    /**
     * Register a resolver for the authenticated user.
     *
     * @return void
     */
    protected function registerUserResolver()
    {
        $this->app->bind(AuthenticatableContract::class, [self::class, 'getAuthPassword']);
    }

    public static function getAuthenticatableContract($app)
    {
        return $app->make('auth')->userResolver()();
    }

    /**
     * Handle the re-binding of the request binding.
     *
     * @return void
     */
    protected function registerRequestRebindHandler()
    {
        $this->app->rebinding('request', [self::class, 'getRegisterRequestBindingHandler']);
    }

    public static function getRegisterRequestBindingHandler($app, $request): void
    {
        $request->setUserResolver(function ($guard = null) use ($app) {
            return call_user_func($app->make('auth')->userResolver(), $guard);
        });
    }

    /**
     * Handle the re-binding of the event dispatcher binding.
     *
     * @return void
     */
    protected function registerEventRebindHandler()
    {
        $this->app->rebinding('events', [self::class, 'getRegisterRebindHandler']);
    }

    public static function getRegisterRebindHandler($app, $dispatcher): void
    {
        if (
            !$app->resolved('auth') ||
            $app->make('auth')->hasResolvedGuards() === false
        ) {
            return;
        }

        if (method_exists($guard = $app->make('auth')->guard(), 'setDispatcher')) {
            $guard->setDispatcher($dispatcher);
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            'auth',
            'auth.driver',
            AuthenticatableContract::class,
        ];
    }
}
