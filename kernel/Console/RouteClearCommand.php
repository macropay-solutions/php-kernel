<?php

namespace MacropaySolutions\Kernel\Console;

use MacropaySolutions\Kernel\Filesystem\Filesystem;

class RouteClearCommand extends Command
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
    protected $name = 'route:clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove the route cache file';

    /**
     * The filesystem instance.
     */
    protected Filesystem $files;

    /**
     * Create a new route clear command instance.
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

        $this->info('Route cache cleared successfully.');
    }
}
