<?php

namespace MacropaySolutions\Framework\Bus;

class PendingCallableDispatch extends PendingDispatch
{
    /**
     * Add a callback to be executed if the job fails.
     */
    public function catch(array $callback): static
    {
        $this->job->onFailure($callback);

        return $this;
    }

    /**
     * Set the number of times the job may be attempted.
     */
    public function tries(int $tries): static
    {
        $this->job->tries = $tries;

        return $this;
    }

    /**
     * Set the number of seconds the job can run before timing out.
     */
    public function timeout(int $seconds): static
    {
        $this->job->timeout = $seconds;

        return $this;
    }

    /**
     * Set the maximum number of unhandled exceptions to allow before failing.
     */
    public function maxExceptions(int $maxExceptions): static
    {
        $this->job->maxExceptions = $maxExceptions;

        return $this;
    }

    /**
     * Set the number of seconds to wait before retrying the job (an array for a different delay per attempt).
     */
    public function backoff(int|array $backoff): static
    {
        $this->job->backoff = $backoff;

        return $this;
    }

    /**
     * Set the time until which the job may be retried.
     */
    public function retryUntil(\DateTimeInterface|int $until): static
    {
        $this->job->retryUntil = $until instanceof \DateTimeInterface ? $until->getTimestamp() : $until;

        return $this;
    }

    /**
     * Mark the job as failed when it times out.
     */
    public function failOnTimeout(bool $failOnTimeout = true): static
    {
        $this->job->failOnTimeout = $failOnTimeout;

        return $this;
    }
}
