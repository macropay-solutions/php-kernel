<?php

namespace MacropaySolutions\Kernel\Cache;

class FileLock extends CacheLock
{
    /**
     * Attempt to acquire the lock.
     *
     * @return bool
     */
    public function acquire()
    {
        return $this->store->add($this->name, $this->owner, $this->seconds);
    }

    public function refresh(?int $seconds = null): bool
    {
        return $this->store->refreshIfOwned(
            $this->name,
            $this->owner,
            $seconds ?? $this->seconds
        );
    }
}
