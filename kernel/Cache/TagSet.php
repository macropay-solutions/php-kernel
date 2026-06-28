<?php

namespace MacropaySolutions\Kernel\Cache;

use MacropaySolutions\Kernel\Contracts\Cache\Store;

class TagSet
{
    /**
     * The cache store implementation.
     *
     * @var \MacropaySolutions\Kernel\Contracts\Cache\Store
     */
    protected $store;

    /**
     * The tag names.
     *
     * @var array
     */
    protected $names = [];

    /**
     * The maximum allowed time-to-live in seconds.
     */
    protected int $ttlCap = 7200;

    /**
     * Create a new TagSet instance.
     *
     * @param \MacropaySolutions\Kernel\Contracts\Cache\Store $store
     * @param array $names
     * @return void
     */
    public function __construct(Store $store, array $names = [])
    {
        $this->store = $store;
        \sort($names);
        $this->names = $names;

        $this->ttlCap = \app()::TAGGED_CACHE_TTL_CAP_SECONDS;
    }

    /**
     * Get the current isolated version for a specific tag.
     */
    protected function tagVersion(string $name): string
    {
        $versionKey = 'tag-version:' . $name;
        $version = $this->store->get($versionKey);

        // EMERGENCY VALVE: Tier 1 (Version) Overflow Protection
        // If we are within 10,000 requests of PHP_INT_MAX, wipe the master key.
        if ($version && (int)$version >= PHP_INT_MAX - 10000) {
            $this->store->forget($versionKey);
            $version = null; // Force the fallback gate below to execute
        }

        if (!$version) {
            if ($this->store->add($versionKey, '1', $this->ttlCap * 2)) {
                return '1';
            }

            return (string)($this->store->get($versionKey) ?: '1');
        }

        return (string)$version;
    }

    /**
     * ATOMIC WRITE LOGIC: Increments the sequence and maps the reference tracking key.
     * Maps: '{tag}-v{version}-{increment} => {cache_key}'
     */
    public function attachKey(string $cacheKey): void
    {
        foreach ($this->names as $name) {
            // 1. Fetch the active, race-isolated version identifier
            $version = $this->tagVersion($name);

            // 2. Atomically increment the sequence counter bound to this specific version
            $counterKey = $this->tagKey($name, $version);
            $increment = $this->store->increment($counterKey);

            // EMERGENCY VALVE: Tier 2 (Sequence) Overflow Protection
            if ($increment !== false && $increment >= PHP_INT_MAX - 10000) {
                // Wipe the master version anchor.
                // We still use this current high increment for THIS specific write to ensure it completes safely,
                // but the NEXT request to hit this tag will naturally re-initialize at v1-1.
                $this->store->forget('tag-version:' . $name);
            }

            // Fallback initialization if the sequence counter doesn't exist yet
            if ($increment === false) {
                $increment = $this->store->add($counterKey, 1, $this->ttlCap + 5) ?
                    1 :
                    $this->store->increment($counterKey);
            }

            // 3. Build the unique pointer key string matching your design requirements
            // $pointerKey = $name . '-v' . $version . '-' . $increment;

            // 4. Physically store the reference pointing to the destination cache key
            $this->store->put($name . '-v' . $version . '-' . $increment, $cacheKey, $this->ttlCap + 5);
        }
    }

    /**
     * Reset all tags in the set.
     *
     * @return void
     */
    public function reset()
    {
        array_walk($this->names, [$this, 'resetTag']);
    }

    /**
     * LAZY EVICTION FLUSH: Instantly increments the version pointer.
     * Old keys are abandoned to let them naturally expire via the ttlCap window.
     *
     * @param string $name
     * @return string
     */
    public function resetTag($name)
    {
        $versionKey = 'tag-version:' . $name;

        // Atomically bump the version tracker. Old keys are instantly orphaned/invalidated.
        // Absolutely zero deletions are performed here, making this an ultra-fast O(1) operation.
        $newVersion = $this->store->increment($versionKey);

        if ($newVersion === false) {
            // Initialize if missing
            if ($this->store->add($versionKey, '1', $this->ttlCap * 2)) {
                return '1';
            }

            return $this->resetTag($name);
        }

        return (string)$newVersion;
    }

    /**
     * Flush all the tags in the set.
     *
     * @return void
     */
    public function flush()
    {
        array_walk($this->names, [$this, 'flushTag']);
    }

    /**
     * Flush the tag from the cache.
     *
     * @param string $name
     */
    public function flushTag($name)
    {
        $this->resetTag($name);
    }

    /**
     * Get a unique namespace that changes when any of the tags are flushed.
     *
     * @return string
     */
    public function getNamespace()
    {
        return implode('|', $this->tagIds());
    }

    /**
     * Get an array of tag identifiers for all the tags in the set.
     * Binds the tag name to the version to prevent cross-contamination.
     *
     * @return array
     */
    protected function tagIds()
    {
        return \array_map(fn(string $name): string => $name . ':' . $this->tagVersion($name), $this->names);
    }

    /**
     * Generate the versioned variant of your master tracking counter.
     * Maps your exact 'tag-index-{tag}' layout per isolated version.
     */
    public function tagKey(string $name, string|int $version = '1'): string
    {
        return 'tag-index-' . $name . '-v' . $version;
    }

    /**
     * Get all the tag names in the set.
     *
     * @return array
     */
    public function getNames()
    {
        return $this->names;
    }

    /**
     * Adjust the expiration time of all tracking references within the active window.
     * Optimized to run at O(1) speeds by removing sequential pointer scanning loops.
     */
    public function touch(): void
    {
        foreach ($this->names as $name) {
            $version = $this->tagVersion($name);

            // 1. Extend the core version indicator key
            $this->store->touch('tag-version:' . $name, $this->ttlCap * 2);

            // 2. Extend the active master sequence counter
            // $counterKey = $this->tagKey($name, $version);
            $this->store->touch($this->tagKey($name, $version), $this->ttlCap + 5);

            // NOTE: Individual pointer keys are already generated with a full $this->ttlCap
            // inside attachKey() and naturally mirror or outlive the capped data payloads.
        }
    }
}
