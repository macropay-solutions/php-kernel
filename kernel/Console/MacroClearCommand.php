<?php

namespace MacropaySolutions\Kernel\Console;

use MacropaySolutions\Kernel\Filesystem\Filesystem;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'macro:clear')]
class MacroClearCommand extends Command
{
    use \MacropaySolutions\Framework\Traitables\MacropaySolutionsKernelConsoleMacroClearCommand;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'macro:clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove the compiled macro traits';

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
        $this->files->deleteDirectory($this->app->bootstrapPath('cache/traitables'));

        $this->info('Macro traits cache cleared successfully.');
    }
}
