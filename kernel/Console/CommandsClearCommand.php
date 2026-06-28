<?php

namespace MacropaySolutions\Kernel\Console;

use MacropaySolutions\Kernel\Filesystem\Filesystem;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'commands:clear')]
class CommandsClearCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'commands:clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove the commands cache file';

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
        $this->files->delete($this->app->getCachedCommandsPath());

        $this->app::setBootstrapCacheFiles($this->app->bootstrapPath('cache'));

        $this->components->info('Commands cache cleared successfully.');
    }
}
