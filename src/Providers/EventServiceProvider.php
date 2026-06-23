<?php

namespace MacropaySolutions\Framework\Providers;

use Illuminate\Console\DiscoverEvents;
use Illuminate\Console\DiscoverEventsAsObservers;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array
     */
    protected $listen = [];

    /**
     * The subscriber classes to register.
     * Avoid using these because they increase the boot time. Use listeners instead.
     *
     * @var array
     */
    protected $subscribe = [];

    /**
     * {@inheritdoc}
     */
    public function register()
    {
        //
    }

    /**
     * Register the application's event listeners.
     *
     * @return void
     */
    public function boot()
    {
        /** @var Dispatcher $events */
        $events = $this->app['events'];

        $events->listen($this->getEvents());

        if ($this->app->resolved('db')) {
            Model::getEventDispatcher()->listen($this->getEventsAsObservers());
        }

        foreach ($this->subscribe as $subscriber) {
            $events->subscribe($subscriber);
        }
    }

    /**
     * Get the events and handlers.
     *
     * @return array
     */
    public function listens()
    {
        return $this->listen;
    }

    protected function getEvents(): array
    {
        if ($this->app->eventsAreCached()) {
            $cache = $this->app::getCachedFileContentsFromMemory($this->app::EVENTS_PHP) ??
                require $this->app->getCachedEventsPath();

            return $cache[static::class] ?? [];
        }

        return \array_merge_recursive($this->discoveredEvents(), $this->listens());
    }

    /**
     * Get the discovered events as observers and listeners for the application.
     */
    public function getEventsAsObservers(): array
    {
        if ($this->app->eventsAsObserversAreCached()) {
            $cache = $this->app::getCachedFileContentsFromMemory($this->app::OBSERVERS_PHP) ??
                require $this->app->getCachedEventsAsObserversPath();

            return $cache[static::class] ?? [];
        }

        return $this->discoveredEventsAsObservers();
    }

    /**
     * Get the discovered events for the application.
     */
    protected function discoveredEvents(): array
    {
        return $this->shouldDiscoverEvents()
            ? $this->discoverEvents()
            : [];
    }

    /**
     * Get the discovered events as observers for the application.
     */
    protected function discoveredEventsAsObservers(): array
    {
        return $this->shouldDiscoverEventsAsObservers()
            ? $this->discoverEventsAsObservers()
            : [];
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }

    /**
     * Determine if events as observers and listeners should be automatically discovered.
     */
    public function shouldDiscoverEventsAsObservers(): bool
    {
        return false;
    }

    /**
     * Discover the events and listeners for the application.
     */
    public function discoverEvents(): array
    {
        return collect($this->discoverEventsWithin())
            ->reject(fn(string $directory): bool => !\is_dir($directory))
            ->reduce(fn(array $discovered, string $directory): array => \array_merge_recursive(
                $discovered,
                DiscoverEvents::within($directory, $this->eventDiscoveryBasePath())
            ), []);
    }

    /**
     * Discover the events and listeners for the application.
     */
    public function discoverEventsAsObservers(): array
    {
        return collect($this->discoverEventsAsObserversWithin())
            ->reject(fn(string $directory): bool => !\is_dir($directory))
            ->reduce(fn(array $discovered, string $directory): array => \array_merge_recursive(
                $discovered,
                DiscoverEventsAsObservers::within($directory, $this->eventDiscoveryBasePath())
            ), []);
    }

    /**
     * Get the listener directories that should be used to discover events.
     */
    protected function discoverEventsWithin(): array
    {
        return [
            $this->app->path() . DIRECTORY_SEPARATOR . 'Listeners',
        ];
    }

    /**
     * Get the observers directories that should be used to discover events as observers.
     */
    protected function discoverEventsAsObserversWithin(): array
    {
        return [
            $this->app->path() . DIRECTORY_SEPARATOR . 'Observers',
        ];
    }

    /**
     * Get the base path to be used during event discovery.
     */
    protected function eventDiscoveryBasePath(): string
    {
        return base_path();
    }
}
