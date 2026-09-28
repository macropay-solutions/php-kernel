<?php

namespace MacropaySolutions\Kernel\Support;

use MacropaySolutions\Kernel\Console\Application as ConsoleApp;
use MacropaySolutions\Kernel\Contracts\Foundation\CachesConfiguration;
use MacropaySolutions\Kernel\Contracts\Foundation\CachesRoutes;
use MacropaySolutions\Kernel\Contracts\Support\DeferrableProvider;
use MacropaySolutions\Kernel\View\Compilers\TemplateCompiler;

abstract class ServiceProvider
{
    /**
     * The application instance.
     *
     * @var \MacropaySolutions\Kernel\Contracts\Foundation\Application
     */
    protected $app;

    /**
     * Create a new service provider instance.
     *
     * @param \MacropaySolutions\Kernel\Contracts\Foundation\Application $app
     * @return void
     */
    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Merge the given configuration with the existing configuration.
     *
     * @param string $path
     * @param string $key
     * @return void
     */
    protected function mergeConfigFrom($path, $key)
    {
        if (!($this->app instanceof CachesConfiguration && $this->app->configurationIsCached())) {
            $config = $this->app->make('config');

            $config->set(
                $key,
                array_merge(
                    require $path,
                    $config->get($key, [])
                )
            );
        }
    }

    /**
     * Load the given routes file if routes are not already cached.
     *
     * @param string $path
     * @return void
     */
    protected function loadRoutesFrom($path)
    {
        if (!($this->app instanceof CachesRoutes && $this->app->routesAreCached())) {
            require $path;
        }
    }

    /**
     * Register the given view components with a custom prefix.
     * Call this ONLY from a deferred service provider like for example
     * @see \MacropaySolutions\Kernel\Mail\MailServiceProvider
     *
     * @param string $prefix
     * @param array $components
     * @return void
     */
    protected function loadViewComponentsAs($prefix, array $components)
    {
        $this->callAfterResolving(TemplateCompiler::class, function ($template) use ($prefix, $components) {
            foreach ($components as $alias => $component) {
                $template->component($component, is_string($alias) ? $alias : null, $prefix);
            }
        });
    }

    /**
     * Set up an after resolving listener, or fire immediately if already resolved.
     */
    protected function callAfterResolving(string $name, callable $callback): void
    {
        if (!\in_array($name, $this->provides(), true)) {
            throw new \RuntimeException($name . ' is not provided by ' . $this::class);
        }

        $this->app->afterResolving($name, $callback);

        if ($this->app->resolved($name)) {
            $callback($this->app->make($name), $this->app);
        }
    }

    /**
     * Register the package's custom commands.
     *
     * @param array|mixed $commands
     * @return void
     */
    public function commands($commands)
    {
        $commands = is_array($commands) ? $commands : func_get_args();

        ConsoleApp::starting(function ($consoleApp) use ($commands) {
            $consoleApp->resolveCommands($commands);
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [];
    }

    /**
     * Get the events that trigger this service provider to register.
     *
     * @return array
     */
    public function when()
    {
        return [];
    }

    /**
     * Determine if the provider is deferred.
     *
     * @return bool
     */
    public function isDeferred()
    {
        return $this instanceof DeferrableProvider;
    }
}
