<?php

namespace MacropaySolutions\Kernel\Tests\Feature\Queue;

use MacropaySolutions\Kernel\Queue\CallQueuedCallable;
use MacropaySolutions\Kernel\Tests\ApplicationTestCase;
use MacropaySolutions\Kernel\Tests\Fixtures\Queue\ExpiringJob;
use MacropaySolutions\Kernel\Tests\Fixtures\Queue\InspectableRetryCommand;
use MacropaySolutions\Kernel\Tests\Fixtures\Queue\OptionsRecordingQueue;

class RetryCommandTest extends ApplicationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        ExpiringJob::$attempts = 0;
    }

    public function test_a_retried_job_gets_a_fresh_retry_until_deadline(): void
    {
        ExpiringJob::new()->dispatch();

        $this->work();
        $this->assertSame(1, ExpiringJob::$attempts);

        $this->travelSeconds(61);
        $this->work();

        $this->assertSame(1, ExpiringJob::$attempts, 'The deadline passed, so the job failed without running.');
        $this->assertSame(1, $this->db()->table('failed_jobs')->count());

        $this->artisan('queue:retry', ['id' => ['all']]);
        $this->work();

        $this->assertSame(2, ExpiringJob::$attempts, 'The retried job runs again within its new deadline.');
    }

    public function test_retry_passes_the_rebuilt_callable_and_no_delay_to_the_queue_options(): void
    {
        $callable = CallQueuedCallable::create([ExpiringJob::class, 'handle']);
        $callable->onGroup('orders');
        $callable->withDeduplicationId('order-1');

        $queue = new OptionsRecordingQueue();
        $job = (object)[
            'queue' => 'jobs.fifo',
            'payload' => \json_encode(['data' => ['command' => \json_encode(\get_object_vars($callable))]]),
        ];

        $command = new InspectableRetryCommand();
        $command->setApp($this->app);
        $command->optionsFor($queue, $job);

        [$passedJob, $passedQueue, $passedDelay] = $queue->calls[0];

        $this->assertInstanceOf(CallQueuedCallable::class, $passedJob);
        $this->assertSame([ExpiringJob::class, 'handle', []], $passedJob->storableCallable);
        $this->assertSame('orders', $passedJob->messageGroup);
        $this->assertSame('order-1', $passedJob->deduplicationId);
        $this->assertSame('jobs.fifo', $passedQueue);
        $this->assertNull($passedDelay);
    }

    private function work(): void
    {
        $this->artisan('queue:work', ['connection' => 'database', '--sleep' => 0]);
    }
}
