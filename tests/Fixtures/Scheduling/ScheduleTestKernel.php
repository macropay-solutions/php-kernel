<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Scheduling;

use MacropaySolutions\Framework\Console\Kernel;
use MacropaySolutions\Kernel\Console\Scheduling\Schedule;
use MacropaySolutions\Kernel\Tests\Fixtures\Queue\RecordingJob;

class ScheduleTestKernel extends Kernel
{
    protected function schedule(Schedule $schedule)
    {
        $schedule->call([ScheduledTasks::class, 'nightlyReport'])->dailyAt('02:00');
        $schedule->call([ScheduledTasks::class, 'everyFiveMinutes'])->everyFiveMinutes();
        $schedule->call([ScheduledTasks::class, 'weekdayCleanup'])->weekdays()->at('09:30');
        $schedule->call([ScheduledTasks::class, 'failing'])->hourlyAt(15);
        $schedule->call([ScheduledTasks::class, 'afterFailing'])->hourlyAt(15);
        $schedule->job([RecordingJob::class, 'handle', ['id' => 99]])->hourlyAt(45);
    }
}
