<?php

namespace MacropaySolutions\Kernel\Console;

use MacropaySolutions\Kernel\Filesystem\Filesystem;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'merge-cached-files:cache')]
class MergeCachedFilesCacheCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'merge-cached-files:cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a merge-cached-files cache file for faster boot times';

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
        $this->callSilent('merge-cached-files:clear');

        \file_put_contents(
            $this->app->getMergedCachedFilesPath(),
            '<?php return ' . \var_export($this->getMap(), true) . ';'
        );

        $this->components->info('merge-cached-files cached successfully.');
    }

    private function getMap(): array
    {
        $cacheDir = $this->app->bootstrapPath('cache');

        $result = [];

        if (\is_dir($cacheDir) && \is_array($files = \scandir($cacheDir))) {
            foreach ($files as $fileName) {
                if (
                    \is_file($cacheDir . DIRECTORY_SEPARATOR . $fileName)
                    && \str_ends_with($fileName, '.php')
                    && !\in_array(
                        $fileName,
                        [$this->app::MERGED_CACHED_FILES_PHP, 'fast_routes.php', 'routes-v7.php'],
                        true
                    )
                ) {
                    $result[$fileName] = require $cacheDir . DIRECTORY_SEPARATOR . $fileName;
                }
            }
        }

        return $result;
    }
}
