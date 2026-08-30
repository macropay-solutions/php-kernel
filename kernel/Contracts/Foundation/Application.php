<?php

namespace MacropaySolutions\Kernel\Contracts\Foundation;

use MacropaySolutions\Kernel\Contracts\Container\Container;

interface Application extends Container
{
    /**
     * Get the version number of the application.
     *
     * @return string
     */
    public function version();

    /**
     * Get the base path of the Kernel installation.
     *
     * @param string $path
     * @return string
     */
    public function basePath($path = '');

    /**
     * Get the path to the bootstrap directory.
     *
     * @param string $path
     * @return string
     */
    public function bootstrapPath($path = '');

    /**
     * Get the path to the application configuration files.
     *
     * @param string $path
     * @return string
     */
    public function configPath($path = '');

    /**
     * Get the path to the database directory.
     *
     * @param string $path
     * @return string
     */
    public function databasePath($path = '');

    /**
     * Get the path to the language files.
     *
     * @param string $path
     * @return string
     */
    public function langPath($path = '');

    /**
     * Get the path to the public directory.
     *
     * @param string $path
     * @return string
     */
    public function publicPath($path = '');

    /**
     * Get the path to the resources directory.
     *
     * @param string $path
     * @return string
     */
    public function resourcePath($path = '');

    /**
     * Get the path to the storage directory.
     *
     * @param string $path
     * @return string
     */
    public function storagePath($path = '');

    /**
     * Get or check the current application environment.
     *
     * @param string|array ...$environments
     * @return string|bool
     */
    public function environment(...$environments);

    /**
     * Determine if the application is running in the console.
     *
     * @return bool
     */
    public function runningInConsole();

    /**
     * Determine if the application is running unit tests.
     *
     * @return bool
     */
    public function runningUnitTests();

    /**
     * Determine if the application is running with debug mode enabled.
     *
     * @return bool
     */
    public function hasDebugModeEnabled();

    /**
     * Get an instance of the maintenance mode manager implementation.
     *
     * @return \MacropaySolutions\Kernel\Contracts\Foundation\MaintenanceMode
     */
    public function maintenanceMode();

    /**
     * Determine if the application is currently down for maintenance.
     *
     * @return bool
     */
    public function isDownForMaintenance();

    /**
     * Register a service provider with the application.
     *
     * @param \MacropaySolutions\Kernel\Support\ServiceProvider|string $provider
     * @param bool $force
     * @return \MacropaySolutions\Kernel\Support\ServiceProvider
     */
    public function register($provider, $force = false);

    /**
     * Resolve a service provider instance from the class name.
     *
     * @param string $provider
     * @return \MacropaySolutions\Kernel\Support\ServiceProvider
     */
    public function resolveProvider($provider);

    /**
     * Boot the application's service providers.
     *
     * @return void
     */
    public function boot();

    /**
     * Run the given array of bootstrap classes.
     *
     * @param array $bootstrappers
     * @return void
     */
    public function bootstrapWith(array $bootstrappers);

    /**
     * Get the current application locale.
     *
     * @return string
     */
    public function getLocale();

    /**
     * Get the application namespace.
     *
     * @return string
     *
     * @throws \RuntimeException
     */
    public function getNamespace();

    /**
     * Get the registered service provider instances if any exist.
     *
     * @param \MacropaySolutions\Kernel\Support\ServiceProvider|string $provider
     * @return array
     */
    public function getProviders($provider);

    /**
     * Determine if the application has been bootstrapped before.
     *
     * @return bool
     */
    public function hasBeenBootstrapped();

    /**
     * Set the current application locale.
     *
     * @param string $locale
     * @return void
     */
    public function setLocale($locale);

    /**
     * Determine if middleware has been disabled for the application.
     *
     * @return bool
     */
    public function shouldSkipMiddleware();

    /**
     * Register a terminating callback with the application.
     *
     * @param callable|string $callback
     * @return \MacropaySolutions\Kernel\Contracts\Foundation\Application
     */
    public function terminating($callback);

    /**
     * Terminate the application.
     *
     * @return void
     */
    public function terminate();
}
