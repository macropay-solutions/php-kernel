<?php

namespace MacropaySolutions\Kernel\Tests\Bus;

use DateTimeImmutable;
use MacropaySolutions\Framework\Bus\PendingCallableDispatch;
use MacropaySolutions\Kernel\Queue\CallQueuedCallable;
use PHPUnit\Framework\TestCase;

/**
 * The job settings of a dispatched array callable can be chained on the pending dispatch.
 */
class PendingCallableDispatchSettingsTest extends TestCase
{
    public function testSettingsAreSetOnTheQueuedCallable(): void
    {
        $job = CallQueuedCallable::create([PendingCallableDispatchSettingsTestJob::class, 'handle', ['id' => 5]]);
        $until = new DateTimeImmutable('2030-01-01 00:00:00');

        $this->pending($job)
            ->tries(3)
            ->timeout(120)
            ->maxExceptions(2)
            ->backoff([10, 60])
            ->retryUntil($until)
            ->failOnTimeout();

        $this->assertSame(3, $job->tries);
        $this->assertSame(120, $job->timeout);
        $this->assertSame(2, $job->maxExceptions);
        $this->assertSame([10, 60], $job->backoff);
        $this->assertSame($until->getTimestamp(), $job->retryUntil);
        $this->assertTrue($job->failOnTimeout);
    }

    public function testRetryUntilAcceptsATimestamp(): void
    {
        $job = CallQueuedCallable::create([PendingCallableDispatchSettingsTestJob::class, 'handle', ['id' => 5]]);

        $this->pending($job)->retryUntil(1893456000)->backoff(30);

        $this->assertSame(1893456000, $job->retryUntil);
        $this->assertSame(30, $job->backoff);
    }

    /**
     * A pending dispatch that does not dispatch on destruction.
     */
    private function pending(CallQueuedCallable $job): PendingCallableDispatch
    {
        return new class ($job) extends PendingCallableDispatch {
            public function __destruct()
            {
            }
        };
    }
}

class PendingCallableDispatchSettingsTestJob
{
    public function handle(int $id): void
    {
    }
}
