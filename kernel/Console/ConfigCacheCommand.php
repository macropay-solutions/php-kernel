<?php

namespace MacropaySolutions\Kernel\Console;

use LogicException;
use MacropaySolutions\Framework\Application;
use MacropaySolutions\Kernel\Contracts\Console\Kernel as ConsoleKernelContract;
use MacropaySolutions\Kernel\Filesystem\Filesystem;
use Throwable;

class ConfigCacheCommand extends Command
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
    protected $name = 'config:cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a cache file for faster configuration loading';

    /**
     * The filesystem instance.
     */
    protected Filesystem $files;

    /**
     * Create a new config cache command instance.
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();

        $this->files = $files;
    }

    /**
     * Execute the console command.
     *
     * @throws \LogicException
     */
    public function handle(): void
    {
        $this->callSilent('config:clear');

        $config = $this->getFreshConfiguration();
        $configPath = $this->app->getCachedConfigPath();

        $this->files->put($configPath, '<?php return ' . \var_export($config, true) . ';' . PHP_EOL);

        try {
            require $configPath;
        } catch (Throwable $e) {
            $this->files->delete($configPath);

            throw new LogicException('Your configuration files are not serializable.', 0, $e);
        }

        $this->info('Configuration cached successfully!');
    }

    /**
     * Boot a fresh copy of the application configuration.
     */
    protected function getFreshConfiguration(): array
    {
        /** @var Application $app */
        $app = require $this->app->bootstrapPath() . '/app.php';

        $app->useStoragePath($this->app->storagePath());

        $app->make(ConsoleKernelContract::class)->bootstrap();

        foreach ($app->getAvailableBindings() as $binding => $resolver) {
            try {
                $app->make($binding);
            } catch (\Throwable $e) {
                $this->info($this->signature . ' notice for availableBinding ' . $binding . ': ' . $e->getMessage());
            }
        }

        return $app->make('config')->all();
    }
}
