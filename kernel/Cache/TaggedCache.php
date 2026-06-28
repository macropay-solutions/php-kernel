<?php

namespace MacropaySolutions\Kernel\Cache;

use MacropaySolutions\Kernel\Contracts\Cache\Store;

class TaggedCache extends Repository
{
    use RetrievesMultipleKeys;

    /**
     * The tag set instance.
     *
     * @var \MacropaySolutions\Kernel\Cache\TagSet
     */
    protected $tags;

    /**
     * The maximum allowed time-to-live in seconds.
     */
    protected int $ttlCap = 7200;

    protected \MacropaySolutions\Kernel\Contracts\Foundation\Application $app;

    /**
     * Create a new tagged cache instance.
     *
     * @param \MacropaySolutions\Kernel\Contracts\Cache\Store $store
     * @param \MacropaySolutions\Kernel\Cache\TagSet $tags
     * @return void
     */
    public function __construct(Store $store, TagSet $tags)
    {
        parent::__construct($store);

        $this->tags = $tags;
        $this->app = \app();
        $this->ttlCap = $this->app::TAGGED_CACHE_TTL_CAP_SECONDS;
    }

    /**
     * Store multiple items and index them under the versioned tracking counters.
     *
     * @param array $values
     * @param int|null $seconds
     * @return bool
     */
    public function putMany(array $values, $seconds = null)
    {
        if (parent::putMany($values, $this->getCappedSeconds($seconds))) {
            // Versioned Pointer Insertion: Generate increments and pointers for every batch item
            foreach (\array_keys($values) as $key) {
                $this->tags->attachKey($this->itemKey($key));
            }

            $this->tags->touch();

            return true;
        }

        return false;
    }

    /**
     * Increment the value of an item in the cache.
     *
     * @param string $key
     * @param mixed $value
     * @return int|bool
     */
    public function increment($key, $value = 1)
    {
        return $this->store->increment($this->itemKey($key), $value);
    }

    /**
     * Decrement the value of an item in the cache.
     *
     * @param string $key
     * @param mixed $value
     * @return int|bool
     */
    public function decrement($key, $value = 1)
    {
        return $this->store->decrement($this->itemKey($key), $value);
    }

    /**
     * Remove all items from the cache.
     *
     * @return bool
     */
    public function flush()
    {
        $this->tags->reset();

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function itemKey($key)
    {
        return $this->taggedItemKey($key);
    }

    /**
     * Get a fully qualified key for a tagged item.
     * Uses composite multi-tag versioning to ensure overlapping tags clear instantly on cross-flushes.
     *
     * @param string $key
     * @return string
     */
    public function taggedItemKey($key)
    {
        return sha1($this->tags->getNamespace()) . ':' . $key;
    }

    /**
     * Fire an event for this cache instance.
     *
     * @param \MacropaySolutions\Kernel\Cache\Events\CacheEvent $event
     * @return void
     */
    protected function event($event)
    {
        parent::event($event->setTags($this->tags->getNames()));
    }

    /**
     * Get the tag set instance.
     *
     * @return \MacropaySolutions\Kernel\Cache\TagSet
     */
    public function getTags()
    {
        return $this->tags;
    }

    /**
     * @inheritdoc
     */
    public function put($key, $value, $ttl = null)
    {
        if (parent::put($key, $value, $this->getCappedSeconds($ttl))) {
            if (!\is_array($key)) {
                // Versioned Pointer Insertion: Map lookup pointer under the active isolated version
                $this->tags->attachKey($this->itemKey($key));
                $this->tags->touch();
            }

            return true;
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function forever($key, $value)
    {
        \app('log')->warning('TaggedCache::forever invoked for key [' . $key .
            ']. Indefinite storage is disabled on multi-tier atomic networks; capping lifespan to ' .
            $this->ttlCap . ' seconds.');

        if (parent::put($key, $value, $this->ttlCap)) {
            $this->tags->attachKey($this->itemKey($key));
            $this->tags->touch();

            return true;
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function add($key, $value, $ttl = null)
    {
        if (parent::add($key, $value, $this->getCappedSeconds($ttl))) {
            if (!\is_array($key)) {
                // Versioned Pointer Insertion: Map lookup pointer under the active isolated version
                $this->tags->attachKey($this->itemKey($key));
                $this->tags->touch();
            }

            return true;
        }

        return false;
    }

    protected function getCappedSeconds($ttl = null): int
    {
        $requestedSeconds = $ttl === null ? null : $this->getSeconds($ttl);

        if ($requestedSeconds === null || $requestedSeconds > $this->ttlCap) {
            $message = 'Tagged cache TTL extension mismatch: Requested ' . ($requestedSeconds ?? 'indefinite') .
                ' seconds, which exceeds the strict security ceiling of ' . $this->ttlCap . ' seconds.';

            if ($this->app->environment('local', 'testing')) {
                throw new \InvalidArgumentException($message);
            }

            \app('log')->critical($message . ' Truncating payload lifecycle to prevent memory index bloat.');

            return $this->ttlCap;
        }

        return $requestedSeconds;
    }
}
