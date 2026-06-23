<?php

namespace Illuminate\Bus;

use Illuminate\Contracts\Cache\Repository as Cache;

class UniqueLock
{
    /**
     * The cache repository implementation.
     *
     * @var \Illuminate\Contracts\Cache\Repository
     */
    protected $cache;

    /**
     * Create a new unique lock manager instance.
     *
     * @param \Illuminate\Contracts\Cache\Repository $cache
     * @return void
     */
    public function __construct(Cache $cache)
    {
        $this->cache = $cache;
    }

    /**
     * Attempt to acquire a lock for the given job.
     *
     * @param mixed $job
     */
    public function acquire($job): bool
    {
        $uniqueFor = method_exists($job, 'uniqueFor')
            ? $job->uniqueFor()
            : ($job->uniqueFor ?? 0);

        $cache = method_exists($job, 'uniqueVia')
            ? $job->uniqueVia()
            : $this->cache;

        return (bool)$cache->lock($this->getKey($job), $uniqueFor <= 0 ? 7200 : $uniqueFor)->get();
    }

    /**
     * Release the lock for the given job.
     *
     * @param mixed $job
     */
    public function release($job): void
    {
        $cache = method_exists($job, 'uniqueVia')
            ? $job->uniqueVia()
            : $this->cache;

        $cache->lock($this->getKey($job))->forceRelease();
    }

    /**
     * Refresh the lock for the given job.
     */
    public function refresh(mixed $job, ?int $seconds = null): bool
    {
        $cache = \method_exists($job, 'uniqueVia') ? $job->uniqueVia() : $this->cache;

        return \method_exists($lock = $cache->lock($this->getKey($job)), 'refresh') && $lock->refresh($seconds);
    }

    /**
     * Generate the lock key for the given job.
     *
     * @param mixed $job
     */
    public static function getKey($job): string
    {
        $uniqueId = method_exists($job, 'uniqueId')
            ? $job->uniqueId()
            : ($job->uniqueId ?? '');

        return 'framework_unique_job:' . get_class($job) . $uniqueId;
    }

    /**
     * Determine the cache store used by the unique job to acquire locks.
     */
    public static function getUniqueJobCacheStore(mixed $job): ?string
    {
        return \method_exists($job, 'uniqueVia')
            ? $job->uniqueVia()->getName()
            : \config('cache.default');
    }
}
