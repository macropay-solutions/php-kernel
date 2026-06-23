<?php

namespace Illuminate\Events;

use Illuminate\Queue\Queue;

class QueuedCallable
{
    /**
     * The underlying Callable.
     */
    public array $callable;

    /**
     * The name of the connection the job should be sent to.
     */
    public ?string $connection;

    /**
     * The name of the queue the job should be sent to.
     */
    public ?string $queue;

    /**
     * The number of seconds before the job should be made available.
     */
    public \DateTimeInterface|\DateInterval|int|null $delay;

    /**
     * All the "catch" callbacks for the queued closure.
     */
    public array $catchCallbacks = [];

    /**
     * Create a new queued callable event listener resolver.
     */
    public function __construct(array $callable)
    {
        $this->callable = Queue::storableCallable($callable);
    }

    /**
     * Set the desired connection for the job.
     */
    public function onConnection(?string $connection): static
    {
        $this->connection = $connection;

        return $this;
    }

    /**
     * Set the desired queue for the job.
     */
    public function onQueue(?string $queue): static
    {
        $this->queue = $queue;

        return $this;
    }

    /**
     * Set the desired delay in seconds for the job.
     */
    public function delay(\DateTimeInterface|\DateInterval|int|null $delay): static
    {
        $this->delay = $delay;

        return $this;
    }

    /**
     * Specify a callable that should be invoked if the queued listener job fails.
     */
    public function catch(array $closure): static
    {
        $this->catchCallbacks[] = Queue::storableCallable($closure);

        return $this;
    }

    /**
     * Resolve the actual event listener callback.
     */
    public function resolve(): \Closure
    {
        return function (...$arguments): void {
            $dispatch = dispatch([
                InvokeQueuedCallable::class,
                'handle',
                [
                    'callable' => $this->callable,
                    'arguments' => $arguments,
                ]
            ])->onConnection($this->connection ?? null)->onQueue($this->queue ?? null)->delay($this->delay ?? null);

            // Properly chain the failure route!
            if ([] !== $this->catchCallbacks) {
                $dispatch->catch([
                    InvokeQueuedCallable::class,
                    'failed',
                    [
                        'callable' => $this->callable,
                        'arguments' => $arguments,
                        'catchCallbacks' => $this->catchCallbacks,
                    ]
                ]);
            }
        };
    }
}
