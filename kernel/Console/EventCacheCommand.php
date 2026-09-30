<?php

namespace MacropaySolutions\Kernel\Console;

use MacropaySolutions\Kernel\Filesystem\Filesystem;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'event:cache')]
class EventCacheCommand extends Command
{
    use \MacropaySolutions\Framework\Traitables\MacropaySolutionsKernelConsoleEventCacheCommand;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'event:cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Discover and cache the application's observers, events and listeners";

    /**
     * The filesystem instance.
     */
    protected Filesystem $files;


    /**
     * Create a new event cache command instance.
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();

        $this->files = $files;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->callSilent('event:clear');

        $this->files->put(
            $this->app->getCachedEventsPath(),
            '<?php return ' . var_export($this->getEvents(), true) . ';'
        );

        $this->files->put(
            $this->app->getCachedEventsAsObserversPath(),
            '<?php return ' . var_export($this->getEventsAsObservers(), true) . ';'
        );

        $this->info('Events cached successfully.');
    }

    /**
     * Get all the events and listeners configured for the application.
     *
     * @return array
     */
    protected function getEvents()
    {
        $events = [];

        foreach ($this->getEventProviders() as $provider) {
            $events[$provider::class] = \array_merge_recursive(
                $provider->shouldDiscoverEvents() ? $provider->discoverEvents() : [],
                $provider->listens()
            );
        }

        return $events;
    }

    /**
     * Get all the events as observers and listeners configured for the application.
     */
    protected function getEventsAsObservers(): array
    {
        $events = [];

        foreach ($this->getEventProviders() as $provider) {
            $events[$provider::class] = $provider->shouldDiscoverEventsAsObservers() ?
                $provider->discoverEventsAsObservers() :
                [];
        }

        return $events;
    }

    protected function getEventProviders(): array
    {
        return $this->app->getProviders(
            \MacropaySolutions\Framework\Providers\EventServiceProvider::class
        );
    }
}
