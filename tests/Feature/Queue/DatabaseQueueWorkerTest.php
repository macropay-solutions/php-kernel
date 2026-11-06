<?php

namespace MacropaySolutions\Kernel\Tests\Feature\Queue;

use MacropaySolutions\Kernel\Tests\ApplicationTestCase;
use MacropaySolutions\Kernel\Tests\Fixtures\Queue\RecordingJob;
use MacropaySolutions\Kernel\Tests\Fixtures\Queue\RetryingJob;
use MacropaySolutions\Kernel\Tests\Fixtures\Queue\UniqueRecordingJob;

/**
 * Jobs go through the real database queue and are run by `queue:work`, which runs one job per call.
 */
class DatabaseQueueWorkerTest extends ApplicationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        RecordingJob::$handled = [];
        RetryingJob::$attempts = 0;
        RetryingJob::$failures = [];
        UniqueRecordingJob::$runs = 0;
    }

    public function test_a_dispatched_job_is_stored_and_the_worker_runs_and_deletes_it(): void
    {
        RecordingJob::new(['id' => 5])->dispatch();

        $this->assertSame(1, $this->db()->table('jobs')->count());

        $this->work();

        $this->assertSame([5], RecordingJob::$handled);
        $this->assertSame(0, $this->db()->table('jobs')->count());
    }

    public function test_jobs_run_in_dispatch_order(): void
    {
        RecordingJob::new(['id' => 1])->dispatch();
        RecordingJob::new(['id' => 2])->dispatch();
        RecordingJob::new(['id' => 3])->dispatch();

        $this->work(3);

        $this->assertSame([1, 2, 3], RecordingJob::$handled);
    }

    public function test_a_job_on_another_queue_is_not_picked_up_by_the_default_queue(): void
    {
        RecordingJob::new(['id' => 1])->onQueue('emails')->dispatch();

        $this->work();
        $this->assertSame([], RecordingJob::$handled);

        $this->work(1, 'emails');
        $this->assertSame([1], RecordingJob::$handled);
    }

    public function test_a_delayed_job_waits_until_it_is_available(): void
    {
        RecordingJob::new(['id' => 9])->delay(60)->dispatch();

        $this->work();
        $this->assertSame([], RecordingJob::$handled);

        $this->travelSeconds(61);
        $this->work();

        $this->assertSame([9], RecordingJob::$handled);
    }

    public function test_a_failing_job_is_released_with_its_backoff_and_failed_after_its_tries(): void
    {
        RetryingJob::new(['reason' => 'gateway down'])->dispatch();

        $this->work();

        $this->assertSame(1, RetryingJob::$attempts);
        $this->assertSame([], RetryingJob::$failures);

        $row = $this->db()->table('jobs')->first();
        $this->assertSame(1, (int)$row->attempts);
        $this->assertNull($row->reserved_at);

        $this->work();
        $this->assertSame(1, RetryingJob::$attempts, 'The backoff of 10 seconds has not passed yet.');

        $this->travelSeconds(11);
        $this->work();

        $this->assertSame(2, RetryingJob::$attempts);
        $this->assertSame(['gateway down|gateway down'], RetryingJob::$failures);
        $this->assertSame(0, $this->db()->table('jobs')->count());

        $this->assertSame(1, $this->db()->table('failed_jobs')->count());

        $failed = $this->db()->table('failed_jobs')->first();
        $this->assertSame('database', $failed->connection);
        $this->assertSame('default', $failed->queue);
        $this->assertStringContainsString('gateway down', $failed->exception);
    }

    public function test_a_failed_job_can_be_retried_with_the_queue_retry_command_when_an_encrypter_is_bound(): void
    {
        RetryingJob::new(['reason' => 'once'])->dispatch();
        $this->work();
        $this->travelSeconds(11);
        $this->work();

        $uuid = $this->db()->table('failed_jobs')->value('uuid');

        $this->assertSame(0, $this->artisan('queue:retry', ['id' => [$uuid]]));
        $this->assertSame(0, $this->db()->table('failed_jobs')->count());
        $this->assertSame(1, $this->db()->table('jobs')->count());

        $this->work();

        $this->assertSame(3, RetryingJob::$attempts, 'The retried job runs again.');
    }

    public function test_the_worker_releases_the_unique_lock_after_the_job_ran(): void
    {
        UniqueRecordingJob::new()->dispatch();
        UniqueRecordingJob::new()->dispatch();

        $this->assertSame(1, $this->db()->table('jobs')->count(), 'The second dispatch is locked out.');

        $this->work();
        $this->assertSame(1, UniqueRecordingJob::$runs);

        UniqueRecordingJob::new()->dispatch();

        $this->assertSame(1, $this->db()->table('jobs')->count(), 'The lock was released, so it can be queued again.');
    }

    private function work(int $times = 1, string $queue = 'default'): void
    {
        for ($i = 0; $i < $times; $i++) {
            $this->artisan('queue:work', ['connection' => 'database', '--queue' => $queue, '--sleep' => 0]);
        }
    }
}
