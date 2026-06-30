<?php

namespace MacropaySolutions\Kernel\Bus;

use MacropaySolutions\Kernel\Container\Container;
use MacropaySolutions\Kernel\Contracts\Bus\Dispatcher;
use MacropaySolutions\Kernel\Contracts\Queue\ShouldQueue;
use MacropaySolutions\Kernel\Queue\CallQueuedCallable;
use MacropaySolutions\Kernel\Queue\InteractsWithQueue;
use MacropaySolutions\Kernel\Support\Collection;

class ChainedBatch implements ShouldQueue
{
    use InstanceDispatchable;
    use Batchable;
    use InteractsWithQueue;
    use Queueable;

    /**
     * The collection of batched jobs.
     */
    public Collection $jobs;

    /**
     * The name of the batch.
     */
    public string $name;

    /**
     * The batch options.
     */
    public array $options;

    /**
     * Create a new chained batch instance.
     */
    public function __construct(PendingBatch $batch)
    {
        $this->jobs = static::prepareNestedBatches($batch->jobs);

        $this->name = $batch->name;
        $this->options = $batch->options;
    }

    /**
     * Prepare any nested batches within the given collection of jobs.
     */
    public static function prepareNestedBatches(Collection $jobs): Collection
    {
        return $jobs->map(fn($job) => match (true) {
            is_array($job) => static::prepareNestedBatches(collect($job))->all(),
            $job instanceof Collection => static::prepareNestedBatches($job),
            $job instanceof PendingBatch => new ChainedBatch($job),
            default => $job,
        });
    }

    /**
     * Handle the job.
     */
    public function handle()
    {
        $this->attachRemainderOfChainToEndOfBatch(
            $this->toPendingBatch()
        )->dispatch();
    }

    /**
     * Convert the chained batch instance into a pending batch.
     *
     * @return \MacropaySolutions\Kernel\Bus\PendingBatch
     */
    public function toPendingBatch()
    {
        $batch = Container::getInstance()->make(Dispatcher::class)->batch($this->jobs);

        $batch->name = $this->name;
        $batch->options = $this->options;

        if ($this->queue) {
            $batch->onQueue($this->queue);
        }

        if ($this->connection) {
            $batch->onConnection($this->connection);
        }

        foreach ($this->chainCatchCallbacks ?? [] as $callback) {
            $batch->catch([self::class, 'handleCatch', ['callback' => $callback]]);
        }

        return $batch;
    }

    /**
     * Move the remainder of the chain to a "finally" batch callback.
     *
     * @return \MacropaySolutions\Kernel\Bus\PendingBatch
     */
    protected function attachRemainderOfChainToEndOfBatch(PendingBatch $batch)
    {
        if ([] !== $this->chained) {
            $rawNext = \array_shift($this->chained);

            // Pass ONLY primitive state to the database. Zero objects enter the payload.
            $batch->finally([self::class, 'handleFinally', [
                'rawNext' => $rawNext,
                'chained' => $this->chained,
                'chainConnection' => $this->chainConnection,
                'chainQueue' => $this->chainQueue,
                'chainCatchCallbacks' => $this->chainCatchCallbacks,
            ]]);

            $this->chained = [];
        }

        return $batch;
    }

    /**
     * @internal
     * Internal array callable target for batch catches.
     */
    public static function handleCatch(Batch $batch, ?\Throwable $exception, mixed $callback): void
    {
        if (!$batch->allowsFailures()) {
            if (\is_string($callback)) {
                $callback = \unserialize($callback);
            }

            // "Shotgun DI" mapping to guarantee 0-reflection cache hits
            \app()->call($callback, [
                'e' => $exception,
                'exception' => $exception,
                'ex' => $exception,
                'error' => $exception,
                \Throwable::class => $exception,
                \Exception::class => $exception,
            ]);
        }
    }

    /**
     * @internal
     * Internal array callable target for batch finally.
     */
    public static function handleFinally(
        Batch $batch,
        mixed $rawNext,
        array $chained,
        ?string $chainConnection,
        ?string $chainQueue,
        ?array $chainCatchCallbacks
    ): void {
        if (!$batch->cancelled()) {
            $next = \is_string($rawNext) ? \unserialize($rawNext) : $rawNext;

            if (\is_array($next)) {
                $next = CallQueuedCallable::create($next);
            }

            $next->chained = $chained;
            $next->onConnection($next->connection ?: $chainConnection);
            $next->onQueue($next->queue ?: $chainQueue);

            $next->chainConnection = $chainConnection;
            $next->chainQueue = $chainQueue;
            $next->chainCatchCallbacks = $chainCatchCallbacks;

            Container::getInstance()->make(Dispatcher::class)->dispatch($next);
        }
    }
}
