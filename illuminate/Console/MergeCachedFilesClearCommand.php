<?php

namespace Illuminate\Console;

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'merge-cached-files:clear')]
class MergeCachedFilesClearCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'merge-cached-files:clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove the merge-cached-files cache file';

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
        $this->files->delete($this->app->getMergedCachedFilesPath());

        $this->app::setBootstrapCacheFiles($this->app->bootstrapPath('cache'));

        $this->components->info('merge-cached-files cache cleared successfully.');
    }
}
