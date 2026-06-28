<?php

namespace MacropaySolutions\Kernel\Console;

use MacropaySolutions\Kernel\Filesystem\Filesystem;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'commands:cache')]
class CommandsCacheCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'commands:cache';

    protected $signature = 'commands:cache {--is-retry}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a commands cache file for faster methods autowiring';

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
        if ($this->app->commandsAreCached()) {
            if ($this->option('is-retry')) {
                $this->components->error('Commands cache failed.');

                return;
            }

            \pclose(\popen('php run commands:clear', 'r'));
            \pclose(\popen('php run ' . $this->name . ' --is-retry', 'r'));

            \clearstatcache();

            if (\file_exists($this->app->getCachedCommandsPath())) {
                $this->components->info('Commands cached successfully.');

                return;
            }

            $this->components->error('Commands cache failed.');

            return;
        }

        \file_put_contents(
            $this->app->getCachedCommandsPath(),
            '<?php return ' . \var_export(
                $this->app->make(\MacropaySolutions\Kernel\Contracts\Console\Kernel::class)->getCommandMap(),
                true
            ) . ';'
        );

        $this->components->info('Commands cached successfully.');
    }
}
