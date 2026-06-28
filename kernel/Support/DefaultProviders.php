<?php

namespace MacropaySolutions\Kernel\Support;

class DefaultProviders
{
    /**
     * The current providers.
     *
     * @var array
     */
    protected $providers;

    /**
     * Create a new default provider collection.
     *
     * @return void
     */
    public function __construct(?array $providers = null)
    {
        $this->providers = $providers ?: [
            \MacropaySolutions\Kernel\Auth\AuthServiceProvider::class,
            \MacropaySolutions\Kernel\Broadcasting\BroadcastServiceProvider::class,
            \MacropaySolutions\Kernel\Bus\BusServiceProvider::class,
            \MacropaySolutions\Kernel\Cache\CacheServiceProvider::class,
            \MacropaySolutions\Kernel\Cookie\CookieServiceProvider::class,
            \MacropaySolutions\Kernel\Database\DatabaseServiceProvider::class,
            \MacropaySolutions\Kernel\Encryption\EncryptionServiceProvider::class,
            \MacropaySolutions\Kernel\Filesystem\FilesystemServiceProvider::class,
            \MacropaySolutions\Kernel\Hashing\HashServiceProvider::class,
            \MacropaySolutions\Kernel\Mail\MailServiceProvider::class,
            \MacropaySolutions\Kernel\Notifications\NotificationServiceProvider::class,
            \MacropaySolutions\Kernel\Pagination\PaginationServiceProvider::class,
            \MacropaySolutions\Kernel\Auth\Passwords\PasswordResetServiceProvider::class,
            \MacropaySolutions\Kernel\Pipeline\PipelineServiceProvider::class,
            \MacropaySolutions\Kernel\Queue\QueueServiceProvider::class,
            \MacropaySolutions\Kernel\Redis\RedisServiceProvider::class,
            \MacropaySolutions\Kernel\Session\SessionServiceProvider::class,
            \MacropaySolutions\Kernel\Translation\TranslationServiceProvider::class,
            \MacropaySolutions\Kernel\Validation\ValidationServiceProvider::class,
            \MacropaySolutions\Kernel\View\ViewServiceProvider::class,
        ];
    }

    /**
     * Merge the given providers into the provider collection.
     *
     * @param array $providers
     * @return static
     */
    public function merge(array $providers)
    {
        $this->providers = array_merge($this->providers, $providers);

        return new static($this->providers);
    }

    /**
     * Replace the given providers with other providers.
     *
     * @param array $items
     * @return static
     */
    public function replace(array $replacements)
    {
        $current = collect($this->providers);

        foreach ($replacements as $from => $to) {
            $key = $current->search($from);

            $current = is_int($key) ? $current->replace([$key => $to]) : $current;
        }

        return new static($current->values()->toArray());
    }

    /**
     * Disable the given providers.
     *
     * @param array $providers
     * @return static
     */
    public function except(array $providers)
    {
        return new static(
            collect($this->providers)
                ->reject(fn($p) => in_array($p, $providers))
                ->values()
                ->toArray()
        );
    }

    /**
     * Convert the provider collection to an array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->providers;
    }
}
