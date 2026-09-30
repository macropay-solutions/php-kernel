<?php

namespace MacropaySolutions\Kernel\Console;

use MacropaySolutions\Kernel\Filesystem\Filesystem;

class ConfigClearCommand extends Command
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
    protected $name = 'config:clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove the configuration cache file';

    /**
     * The filesystem instance.
     */
    protected Filesystem $files;

    /**
     * Create a new config clear command instance.
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
        $this->files->delete($this->app->getCachedConfigPath());

        $this->app::setBootstrapCacheFiles($this->app->bootstrapPath('cache'));

        $this->info('Configuration cache cleared!');
    }
}
