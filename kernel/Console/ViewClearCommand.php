<?php

namespace MacropaySolutions\Kernel\Console;

use MacropaySolutions\Kernel\Filesystem\Filesystem;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'view:clear')]
class ViewClearCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'view:clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear all compiled view files';

    /**
     * The filesystem instance.
     *
     * @var \MacropaySolutions\Kernel\Filesystem\Filesystem
     */
    protected $files;

    /**
     * Create a new config clear command instance.
     *
     * @param \MacropaySolutions\Kernel\Filesystem\Filesystem $files
     * @return void
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();

        $this->files = $files;
    }

    /**
     * Execute the console command.
     *
     * @return void
     *
     * @throws \RuntimeException
     */
    public function handle()
    {
        $path = $this->app['config']['view.compiled'];

        if (!$path) {
            throw new RuntimeException('View path not found.');
        }

        $this->app['view.engine.resolver']
            ->resolve('template')
            ->forgetCompiledOrNotExpired();

        foreach ($this->files->glob("{$path}/*") as $view) {
            $this->files->delete($view);
        }

        $this->components->info('Compiled views cleared successfully.');
    }
}
