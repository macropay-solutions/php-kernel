<?php

namespace MacropaySolutions\Framework\Bus;

use MacropaySolutions\Kernel\Bus\UniqueLock;
use MacropaySolutions\Kernel\Container\Container;
use MacropaySolutions\Kernel\Contracts\Bus\Dispatcher;
use MacropaySolutions\Kernel\Contracts\Cache\Repository as Cache;
use MacropaySolutions\Kernel\Contracts\Queue\ShouldBeUnique;

class PendingDispatch
{
    /**
     * The job.
     *
     * @var mixed
     */
    protected $job;

    /**
     * Indicates if the job should be dispatched immediately after sending the response.
     */
    protected bool $afterResponse = false;


    /**
     * Create a new pending job dispatch.
     *
     * @param mixed $job
     * @return void
     */
    public function __construct($job)
    {
        $this->job = $job;
    }

    /**
     * Set the desired connection for the job.
     *
     * @param string|null $connection
     * @return $this
     */
    public function onConnection($connection)
    {
        $this->job->onConnection($connection);

        return $this;
    }

    /**
     * Set the desired queue for the job.
     *
     * @param string|null $queue
     * @return $this
     */
    public function onQueue($queue)
    {
        $this->job->onQueue($queue);

        return $this;
    }

    /**
     * Set the desired job "group".
     *
     * This feature is only supported by some queues, such as Amazon SQS.
     */
    public function onGroup(\UnitEnum|string $group): static
    {
        $this->job->onGroup($group);

        return $this;
    }

    /**
     * Indicate that the job should be dispatched after the response is sent to the browser.
     */
    public function afterResponse(): static
    {
        $this->afterResponse = true;

        return $this;
    }

    /**
     * Determine if the job should be dispatched.
     */
    protected function shouldDispatch(): bool
    {
        if (!$this->job instanceof ShouldBeUnique) {
            return true;
        }

        return (new UniqueLock(Container::getInstance()->make(Cache::class)))
            ->acquire($this->job);
    }

    /**
     * Handle the object's destruction.
     */
    public function __destruct()
    {
        if (!$this->shouldDispatch()) {
            return;
        }

        if ($this->afterResponse) {
            \app(Dispatcher::class)->dispatchAfterResponse($this->job);

            return;
        }

        \app(Dispatcher::class)->dispatch($this->job);
    }
}
