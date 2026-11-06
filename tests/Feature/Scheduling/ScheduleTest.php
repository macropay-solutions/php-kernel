<?php

namespace MacropaySolutions\Kernel\Tests\Feature\Scheduling;

use Carbon\Carbon;
use MacropaySolutions\Kernel\Console\Scheduling\Schedule;
use MacropaySolutions\Kernel\Contracts\Console\Kernel as ConsoleKernelContract;
use MacropaySolutions\Kernel\Tests\ApplicationTestCase;
use MacropaySolutions\Kernel\Tests\Fixtures\Queue\RecordingJob;
use MacropaySolutions\Kernel\Tests\Fixtures\Scheduling\ScheduledTasks;
use MacropaySolutions\Kernel\Tests\Fixtures\Scheduling\ScheduleTestKernel;

/**
 * The schedule defined on the console kernel, run with schedule:run at fixed times.
 */
class ScheduleTest extends ApplicationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        ScheduledTasks::$ran = [];
        RecordingJob::$handled = [];
        $this->app->singleton(ConsoleKernelContract::class, ScheduleTestKernel::class);
    }

    public function test_only_the_due_events_run(): void
    {
        $this->runScheduleAt('2026-11-06 02:00:00');

        $this->assertSame(['nightly', 'five'], ScheduledTasks::$ran);
    }

    public function test_frequency_and_weekday_constraints(): void
    {
        $this->runScheduleAt('2026-11-06 09:30:00');
        $this->assertSame(['five', 'weekday'], ScheduledTasks::$ran, 'Friday');

        ScheduledTasks::$ran = [];
        $this->runScheduleAt('2026-11-07 09:30:00');
        $this->assertSame(['five'], ScheduledTasks::$ran, 'Saturday');

        ScheduledTasks::$ran = [];
        $this->runScheduleAt('2026-11-06 09:31:00');
        $this->assertSame([], ScheduledTasks::$ran);
    }

    public function test_a_failing_task_does_not_stop_the_next_ones(): void
    {
        $this->runScheduleAt('2026-11-06 10:15:00');

        $this->assertSame(['five', 'failing', 'after-failing'], ScheduledTasks::$ran);
    }

    public function test_a_scheduled_job_is_pushed_to_the_queue(): void
    {
        $this->runScheduleAt('2026-11-06 10:45:00');

        $this->assertSame([], RecordingJob::$handled);
        $this->assertSame(1, $this->db()->table('jobs')->count());

        $this->artisan('queue:work', ['connection' => 'database', '--sleep' => 0]);

        $this->assertSame([99], RecordingJob::$handled);
    }

    public function test_the_schedule_is_resolvable_and_lists_its_events(): void
    {
        $this->app->make(ConsoleKernelContract::class);

        $events = $this->app->make(Schedule::class)->events();

        $this->assertCount(6, $events);
        $this->assertSame('0 2 * * *', $events[0]->expression);
        $this->assertSame('*/5 * * * *', $events[1]->expression);
        $this->assertSame('30 9 * * 1-5', $events[2]->expression);
    }

    private function runScheduleAt(string $time): void
    {
        Carbon::setTestNow(Carbon::parse($time, 'UTC'));

        $this->artisan('schedule:run');
    }
}
