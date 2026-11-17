<?php

namespace MacropaySolutions\Kernel\Bus;

use MacropaySolutions\Kernel\Cache\Repository;
use MacropaySolutions\Kernel\Contracts\Cache\Lock as LockContract;
use MacropaySolutions\Kernel\Contracts\Cache\Repository as Cache;

class UniqueLock
{
    /**
     * The cache repository implementation.
     *
     * @var \MacropaySolutions\Kernel\Contracts\Cache\Repository
     */
    protected $cache;

    /**
     * The job objects whose lock was taken by acquire() in this process, with that lock.
     */
    private static ?\WeakMap $acquired = null;

    /**
     * Create a new unique lock manager instance.
     *
     * @param \MacropaySolutions\Kernel\Contracts\Cache\Repository $cache
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

        $lock = $cache->fwd()->lock($this->getKey($job), $uniqueFor <= 0 ? 7200 : $uniqueFor);
        $acquired = (bool)$lock->get();

        if ($acquired && \is_object($job)) {
            self::$acquired ??= new \WeakMap();
            self::$acquired[$job] = $lock;
        }

        return $acquired;
    }

    /**
     * Whether acquire() took the lock for this job object in this process.
     * A unique job pushed without dispatch() (Queue::push(), Bus::dispatch()) never took it.
     */
    public static function acquiredFor(mixed $job): bool
    {
        return \is_object($job) && isset(self::$acquired[$job]);
    }

    /**
     * Release the lock only if this job object took it: never another dispatch's lock.
     * The lock taken by acquire() is released by its owner, so a lock already released (by the job's failure
     * handling) and taken again by another dispatch is not released a second time.
     */
    public function releaseIfAcquired(mixed $job): void
    {
        $lock = \is_object($job) ? (self::$acquired[$job] ?? null) : null;

        if ($lock instanceof LockContract) {
            unset(self::$acquired[$job]);
            $lock->release();
        }
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

        $cache->fwd()->lock($this->getKey($job))->forceRelease();

        if (\is_object($job)) {
            unset(self::$acquired[$job]);
        }
    }

    /**
     * Refresh the lock for the given job.
     */
    public function refresh(mixed $job, ?int $seconds = null): bool
    {
        $cache = \method_exists($job, 'uniqueVia')
            ? $job->uniqueVia()
            : $this->cache;

        return $cache->fwd()->lock($this->getKey($job))->refresh($seconds);
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
        $cache = \method_exists($job, 'uniqueVia') ? $job->uniqueVia() : null;

        return ($cache instanceof Repository ? $cache->getName() : null) ?? \config('cache.default');
    }
}
