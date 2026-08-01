<?php

namespace MacropaySolutions\Kernel\Console;

use MacropaySolutions\Kernel\Support\Collection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

#[AsCommand(name: 'view:cache')]
class ViewCacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'view:cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Compile all the application's Template templates";

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->callSilent('view:clear');

        $this->paths()->each(function ($path) {
            $prefix = $this->output->isVeryVerbose() ? '<fg=yellow;options=bold>DIR</> ' : '';

            $this->components->task($prefix . $path, null, OutputInterface::VERBOSITY_VERBOSE);

            $this->compileViews($this->templateFilesIn([$path]));
        });

        $this->newLine();

        $this->components->info('Template templates cached successfully.');
    }

    /**
     * Compile the given view files.
     *
     * @param \MacropaySolutions\Kernel\Support\Collection $views
     * @return void
     */
    protected function compileViews(Collection $views)
    {
        $compiler = $this->app['view']->getEngineResolver()->resolve('template')->getCompiler();

        $views->map(function (SplFileInfo $file) use ($compiler) {
            $this->components->task(
                '    ' . $file->getRelativePathname(),
                null,
                OutputInterface::VERBOSITY_VERY_VERBOSE
            );

            $compiler->compile($file->getRealPath());
        });

        if ($this->output->isVeryVerbose()) {
            $this->newLine();
        }
    }

    /**
     * Get the Template files in the given path.
     *
     * @param array $paths
     * @return \MacropaySolutions\Kernel\Support\Collection
     */
    protected function templateFilesIn(array $paths)
    {
        $extensions = collect($this->app['view']->getExtensions())
            ->filter(fn($value) => $value === 'template')
            ->keys()
            ->map(fn($extension) => "*.{$extension}")
            ->all();

        return collect(
            Finder::create()
                ->in($paths)
                ->exclude('vendor')
                ->name($extensions)
                ->files()
        );
    }

    /**
     * Get all the possible view paths.
     *
     * @return \MacropaySolutions\Kernel\Support\Collection
     */
    protected function paths()
    {
        $finder = $this->app['view']->getFinder();

        return collect($finder->getPaths())->merge(
            collect($finder->getHints())->flatten()
        );
    }
}
