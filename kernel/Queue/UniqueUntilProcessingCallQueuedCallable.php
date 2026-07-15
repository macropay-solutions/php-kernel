<?php

namespace MacropaySolutions\Kernel\Queue;

use MacropaySolutions\Kernel\Contracts\Queue\ShouldBeUniqueUntilProcessing;

class UniqueUntilProcessingCallQueuedCallable extends CallQueuedCallable implements ShouldBeUniqueUntilProcessing
{
    protected ?string $uniqueId = null;

    protected ?int $uniqueFor = null;

    protected ?string $uniqueCacheStore = null;

    /**
     * Get the unique key. Lazy-computes a deterministic default if null.
     */
    public function uniqueId(): string
    {
        return $this->uniqueId ??= $this->storableCallable[0] . '@' . $this->storableCallable[1] . ':' .
            \hash('sha256', \json_encode($this->storableCallable[2] ?? []));
    }

    /**
     * Get the lock expiration. Defaults to 2 hours (7200s) if null.
     */
    public function uniqueFor(): ?int
    {
        return $this->uniqueFor ?? 7200;
    }

    public function uniqueVia()
    {
        return (string)$this->uniqueCacheStore !== ''
            ? \app(\MacropaySolutions\Kernel\Contracts\Cache\Factory::class)->store($this->uniqueCacheStore)
            : \app(\MacropaySolutions\Kernel\Contracts\Cache\Repository::class);
    }

    /**
     * Set the unique ID fluently.
     */
    public function setUniqueId(?string $uniqueId): static
    {
        $this->uniqueId = $uniqueId;

        return $this;
    }

    /**
     * Set the lock expiration fluently.
     */
    public function setUniqueFor(?int $uniqueFor): static
    {
        $this->uniqueFor = $uniqueFor;

        return $this;
    }

    /**
     * Set the designated cache store fluently.
     */
    public function setUniqueCacheStore(?string $uniqueCacheStore): static
    {
        $this->uniqueCacheStore = $uniqueCacheStore;

        return $this;
    }
}