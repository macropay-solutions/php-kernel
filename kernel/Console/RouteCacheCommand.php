<?php

namespace MacropaySolutions\Kernel\Console;

use MacropaySolutions\Kernel\Filesystem\Filesystem;

class RouteCacheCommand extends Command
{
    /**
     * The Framework application instance.
     *
     * @var \MacropaySolutions\Framework\Application
     */
    protected $app;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'route:cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a route cache file for faster route registration';

    /**
     * The filesystem instance.
     */
    protected Filesystem $files;

    /**
     * Create a new route command instance.
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();

        $this->files = $files;
    }

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->files->delete($this->app->getCachedRoutesPath());
        $this->files->delete($this->app->getCachedFastRoutesPath());

        $this->app::setBootstrapCacheFiles($this->app->bootstrapPath('cache'));

        $this->app->bootstrapRouter();
        $this->app->instance('router', $this->app->router);
        $this->app->router->registerRoutes();

        $this->files->put(
            $path = $this->app->getCachedRoutesPath(),
            '<?php return ' . \var_export($this->app->router->getCacheData(), true) . ';' . PHP_EOL
        );

        try {
            $c = require $path;

            if ($c['complexRoutes'] !== []) {
                \FastRoute\cachedDispatcher(function (\FastRoute\RouteCollector $r) use ($c): void {
                    foreach ($c['complexRoutes'] as $route) {
                        $r->addRoute($route['method'], $route['uri'], $route['action']);
                    }
                }, [
                    'cacheFile' => $this->app->getCachedFastRoutesPath()
                ]);
            }
        } catch (\Throwable $e) {
            $this->files->delete($path);
            $this->files->delete($this->app->getCachedFastRoutesPath());

            throw new \LogicException('Your routes are not serializable.', 0, $e);
        }

        $this->info('Routes cached successfully.');
    }
}
