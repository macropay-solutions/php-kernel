<?php

namespace MacropaySolutions\Kernel\Tests\Bus;

use Carbon\CarbonImmutable;
use Closure;
use MacropaySolutions\Kernel\Bus\Batch;
use MacropaySolutions\Kernel\Bus\BatchRepository;
use MacropaySolutions\Kernel\Bus\PendingBatch;
use MacropaySolutions\Kernel\Contracts\Queue\Factory;
use MacropaySolutions\Kernel\Queue\CallQueuedCallable;
use PHPUnit\Framework\TestCase;

/**
 * Adding a chain to a batch without a queue / connection option wiped the queue and connection set on the chain.
 */
class BatchChainQueueTest extends TestCase
{
    public function testChainKeepsItsQueueWhenTheBatchHasNone(): void
    {
        $pushed = $this->addChain([]);

        $this->assertSame('high', $pushed[0]->queue);
        $this->assertSame('redis', $pushed[0]->connection);
    }

    public function testBatchQueueOverridesTheChain(): void
    {
        $pushed = $this->addChain(['queue' => 'batch', 'connection' => 'sqs']);

        $this->assertSame('batch', $pushed[0]->queue);
        $this->assertSame('sqs', $pushed[0]->connection);
    }

    private function addChain(array $options): array
    {
        $queue = new class {
            public array $jobs = [];

            public function bulk($jobs, $data = '', $queue = null): void
            {
                $this->jobs = $jobs;
            }
        };
        $factory = new class ($queue) implements Factory {
            public function __construct(private object $queue)
            {
            }

            public function connection($name = null)
            {
                return $this->queue;
            }

            public function fwd(): \MacropaySolutions\Kernel\Contracts\Queue\Queue
            {
                throw new \LogicException('Not used.');
            }
        };
        $repository = new class implements BatchRepository {
            public ?Batch $batch = null;

            public function get($limit, $before)
            {
                return [];
            }

            public function find(string $batchId)
            {
                return $this->batch;
            }

            public function store(PendingBatch $batch)
            {
            }

            public function incrementTotalJobs(string $batchId, int $amount)
            {
            }

            public function decrementPendingJobs(string $batchId, string $jobId)
            {
            }

            public function incrementFailedJobs(string $batchId, string $jobId)
            {
            }

            public function markAsFinished(string $batchId)
            {
            }

            public function cancel(string $batchId)
            {
            }

            public function delete(string $batchId)
            {
            }

            public function transaction(Closure $callback)
            {
                return $callback();
            }
        };
        $batch = new Batch($factory, $repository, 'batch-id', 'name', 0, 0, 0, [], $options, CarbonImmutable::now());
        $repository->batch = $batch;

        $first = CallQueuedCallable::create([BatchChainQueueTestJob::class, 'handle', ['id' => 1]])
            ->onQueue('high')
            ->onConnection('redis');

        $batch->add([[$first, [BatchChainQueueTestJob::class, 'handle', ['id' => 2]]]]);

        return $queue->jobs;
    }
}

class BatchChainQueueTestJob
{
    public function handle(int $id): void
    {
    }
}
