<?php

namespace MacropaySolutions\Kernel\Console\Scheduling;

use MacropaySolutions\Kernel\Console\Command;
use MacropaySolutions\Kernel\Console\Events\ScheduledBackgroundTaskFinished;
use MacropaySolutions\Kernel\Console\Events\ScheduledTaskFailed;
use MacropaySolutions\Kernel\Contracts\Debug\ExceptionHandler;
use MacropaySolutions\Kernel\Contracts\Events\Dispatcher;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'schedule:finish')]
class ScheduleFinishCommand extends Command
{
    use \MacropaySolutions\Framework\Traitables\MacropaySolutionsKernelConsoleSchedulingScheduleFinishCommand;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'schedule:finish {id} {code=0}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Handle the completion of a scheduled command';

    /**
     * Indicates whether the command should be shown in the Run command list.
     *
     * @var bool
     */
    protected $hidden = true;

    /**
     * Execute the console command.
     *
     * @param \MacropaySolutions\Kernel\Console\Scheduling\Schedule $schedule
     * @return void
     */
    public function handle(Schedule $schedule)
    {
        collect($schedule->events())->filter(function ($value) {
            return $value->mutexName() == $this->argument('id');
        })->each(function ($event) {
            $event->finish($this->app, $this->argument('code'));

            $this->app->make(Dispatcher::class)->dispatch(new ScheduledBackgroundTaskFinished($event));

            if (0 !== $event->exitCode) {
                $exception = new RuntimeException(\sprintf(
                    'Scheduled task [%s] failed with exit code [%d].',
                    $event->getSummaryForDisplay(),
                    $event->exitCode
                ));

                $this->app->make(Dispatcher::class)->dispatch(new ScheduledTaskFailed($event, $exception));
                $this->app->make(ExceptionHandler::class)->report($exception);
            }
        });
    }
}
