<?php

namespace MacropaySolutions\Kernel\Tests\Feature\Queue;

use MacropaySolutions\Kernel\Tests\ApplicationTestCase;
use MacropaySolutions\Kernel\Tests\Fixtures\Events\EventLog;
use MacropaySolutions\Kernel\Tests\Fixtures\Events\PaymentSettled;
use MacropaySolutions\Kernel\Tests\Fixtures\Events\RecordSettlement;
use MacropaySolutions\Kernel\Tests\Fixtures\Queue\RecordingJob;

/**
 * Jobs and events marked "after commit" must wait for the outermost transaction and vanish on rollback.
 */
class AfterCommitTest extends ApplicationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        EventLog::$entries = [];
        RecordingJob::$handled = [];
        $this->app->make('config')->set('queue.connections.deferred', [
            'driver' => 'database',
            'connection' => 'sqlite',
            'table' => 'jobs',
            'queue' => 'default',
            'retry_after' => 90,
            'after_commit' => true,
        ]);
    }

    public function test_an_after_commit_job_is_queued_only_when_the_transaction_commits(): void
    {
        $this->db()->beginTransaction();
        RecordingJob::new(['id' => 1])->afterCommit()->dispatch();

        $this->assertSame(0, $this->jobs());

        $this->db()->commit();

        $this->assertSame(1, $this->jobs());
    }

    public function test_an_after_commit_job_is_dropped_when_the_transaction_rolls_back(): void
    {
        $this->db()->beginTransaction();
        RecordingJob::new(['id' => 1])->afterCommit()->dispatch();
        $this->db()->rollBack();

        $this->assertSame(0, $this->jobs());
    }

    public function test_an_after_commit_job_waits_for_the_outermost_transaction(): void
    {
        $this->db()->beginTransaction();
        $this->db()->beginTransaction();
        RecordingJob::new(['id' => 1])->afterCommit()->dispatch();
        $this->db()->commit();

        $this->assertSame(0, $this->jobs());

        $this->db()->commit();

        $this->assertSame(1, $this->jobs());
    }

    public function test_a_job_dispatched_in_a_rolled_back_savepoint_is_dropped_even_if_the_outer_commits(): void
    {
        $this->db()->beginTransaction();
        RecordingJob::new(['id' => 1])->afterCommit()->dispatch();
        $this->db()->beginTransaction();
        RecordingJob::new(['id' => 2])->afterCommit()->dispatch();
        $this->db()->rollBack();
        $this->db()->commit();

        $this->assertSame(1, $this->jobs());

        $this->work();

        $this->assertSame([1], RecordingJob::$handled);
    }

    public function test_a_job_without_after_commit_is_queued_inside_the_transaction(): void
    {
        $this->db()->beginTransaction();
        RecordingJob::new(['id' => 1])->dispatch();

        $this->assertSame(1, $this->jobs());

        $this->db()->commit();
    }

    public function test_an_after_commit_job_outside_a_transaction_is_queued_immediately(): void
    {
        RecordingJob::new(['id' => 1])->afterCommit()->dispatch();

        $this->assertSame(1, $this->jobs());
    }

    public function test_the_connection_after_commit_option_defers_jobs_without_their_own_flag(): void
    {
        $this->db()->beginTransaction();
        RecordingJob::new(['id' => 1])->onConnection('deferred')->dispatch();

        $this->assertSame(0, $this->jobs());

        $this->db()->commit();

        $this->assertSame(1, $this->jobs());
    }

    public function test_before_commit_overrides_the_connection_after_commit_option(): void
    {
        $this->db()->beginTransaction();
        RecordingJob::new(['id' => 1])->onConnection('deferred')->beforeCommit()->dispatch();

        $this->assertSame(1, $this->jobs());

        $this->db()->commit();
    }

    public function test_the_transaction_closure_defers_after_commit_jobs_until_it_returns(): void
    {
        $this->db()->transaction(function (): void {
            RecordingJob::new(['id' => 1])->afterCommit()->dispatch();

            $this->assertSame(0, $this->jobs());
        });

        $this->assertSame(1, $this->jobs());
    }

    public function test_an_after_commit_event_reaches_listeners_only_after_commit(): void
    {
        $this->app->make('events')->listen(PaymentSettled::class, RecordSettlement::class);

        $this->db()->beginTransaction();
        $this->app->make('events')->dispatch(new PaymentSettled(3));

        $this->assertSame([], EventLog::$entries);

        $this->db()->commit();

        $this->assertSame(['settled:3'], EventLog::$entries);
    }

    public function test_an_after_commit_event_is_dropped_on_rollback(): void
    {
        $this->app->make('events')->listen(PaymentSettled::class, RecordSettlement::class);

        $this->db()->beginTransaction();
        $this->app->make('events')->dispatch(new PaymentSettled(3));
        $this->db()->rollBack();

        $this->assertSame([], EventLog::$entries);
    }

    public function test_db_after_commit_callbacks_run_after_commit(): void
    {
        $ran = [];

        $this->db()->beginTransaction();
        $this->db()->afterCommit(function () use (&$ran): void {
            $ran[] = 'done';
        });

        $this->assertSame([], $ran);

        $this->db()->commit();

        $this->assertSame(['done'], $ran);
    }

    private function jobs(): int
    {
        return $this->db()->table('jobs')->count();
    }

    private function work(): void
    {
        $this->artisan('queue:work', ['connection' => 'database', '--sleep' => 0]);
    }
}
