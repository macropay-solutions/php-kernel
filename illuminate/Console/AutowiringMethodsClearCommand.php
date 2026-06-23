<?php

namespace Illuminate\Console;

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'autowiring:clear')]
class AutowiringMethodsClearCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'autowiring:clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove the autowiring cache file';

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
        $this->files->delete($this->app->getCachedAutowiringPath());
        $this->files->delete($this->app->getCachedAbstractToTypeOfResolvingCallbacksEventsAsKeysPath());

        $this->app::setBootstrapCacheFiles($this->app->bootstrapPath('cache'));

        $this->components->info('Autowiring cache cleared successfully.');
    }
}
