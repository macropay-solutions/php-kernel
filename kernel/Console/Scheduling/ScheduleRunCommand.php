<?php

namespace MacropaySolutions\Kernel\Console\Scheduling;

use MacropaySolutions\Kernel\Console\Application;
use MacropaySolutions\Kernel\Console\Command;
use MacropaySolutions\Kernel\Console\Events\ScheduledTaskFailed;
use MacropaySolutions\Kernel\Console\Events\ScheduledTaskFinished;
use MacropaySolutions\Kernel\Console\Events\ScheduledTaskSkipped;
use MacropaySolutions\Kernel\Console\Events\ScheduledTaskStarting;
use MacropaySolutions\Kernel\Contracts\Cache\Repository as Cache;
use MacropaySolutions\Kernel\Contracts\Debug\ExceptionHandler;
use MacropaySolutions\Kernel\Contracts\Events\Dispatcher;
use MacropaySolutions\Kernel\Support\Carbon;
use MacropaySolutions\Kernel\Support\Sleep;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;

#[AsCommand(name: 'schedule:run')]
class ScheduleRunCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'schedule:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run the scheduled commands';

    /**
     * The schedule instance.
     *
     * @var \MacropaySolutions\Kernel\Console\Scheduling\Schedule
     */
    protected $schedule;

    /**
     * The 24 hour timestamp this scheduler command started running.
     *
     * @var \MacropaySolutions\Kernel\Support\Carbon
     */
    protected $startedAt;

    /**
     * Check if any events ran.
     *
     * @var bool
     */
    protected $eventsRan = false;

    /**
     * The event dispatcher.
     *
     * @var \MacropaySolutions\Kernel\Contracts\Events\Dispatcher
     */
    protected $dispatcher;

    /**
     * The exception handler.
     *
     * @var \MacropaySolutions\Kernel\Contracts\Debug\ExceptionHandler
     */
    protected $handler;

    /**
     * The cache store implementation.
     *
     * @var \MacropaySolutions\Kernel\Contracts\Cache\Repository
     */
    protected $cache;

    /**
     * The PHP binary used by the command.
     *
     * @var string
     */
    protected $phpBinary;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->startedAt = \appDate()->now();

        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @param \MacropaySolutions\Kernel\Console\Scheduling\Schedule $schedule
     * @param \MacropaySolutions\Kernel\Contracts\Events\Dispatcher $dispatcher
     * @param \MacropaySolutions\Kernel\Contracts\Cache\Repository $cache
     * @param \MacropaySolutions\Kernel\Contracts\Debug\ExceptionHandler $handler
     * @return void
     */
    public function handle(Schedule $schedule, Dispatcher $dispatcher, Cache $cache, ExceptionHandler $handler)
    {
        $this->schedule = $schedule;
        $this->dispatcher = $dispatcher;
        $this->cache = $cache;
        $this->handler = $handler;
        $this->phpBinary = Application::phpBinary();

        $this->newLine();

        $events = $this->schedule->dueEvents($this->app);

        if ($events->contains->isRepeatable()) {
            $this->clearInterruptSignal();
        }

        foreach ($events as $event) {
            if (!$event->filtersPass($this->app)) {
                $this->dispatcher->dispatch(new ScheduledTaskSkipped($event));

                continue;
            }

            if ($event->onOneServer) {
                $this->runSingleServerEvent($event);
            } else {
                $this->runEvent($event);
            }

            $this->eventsRan = true;
        }

        if ($events->contains->isRepeatable()) {
            $this->repeatEvents($events->filter->isRepeatable());
        }

        if (!$this->eventsRan) {
            $this->components->info('No scheduled commands are ready to run.');
        } else {
            $this->newLine();
        }
    }

    /**
     * Run the given single server event.
     *
     * @param \MacropaySolutions\Kernel\Console\Scheduling\Event $event
     * @return void
     */
    protected function runSingleServerEvent($event)
    {
        if ($this->schedule->serverShouldRun($event, $this->startedAt)) {
            $this->runEvent($event);
        } else {
            $this->components->info(
                sprintf(
                    'Skipping [%s], as command already run on another server.',
                    $event->getSummaryForDisplay()
                )
            );
        }
    }

    /**
     * Run the given event.
     *
     * @param \MacropaySolutions\Kernel\Console\Scheduling\Event $event
     * @return void
     */
    protected function runEvent($event)
    {
        $summary = $event->getSummaryForDisplay();

        $command = $event instanceof CallbackEvent
            ? $summary
            : trim(str_replace($this->phpBinary, '', $event->command));

        $description = sprintf(
            '<fg=gray>%s</> Running [%s]%s',
            Carbon::now()->format('Y-m-d H:i:s'),
            $command,
            $event->runInBackground ? ' in background' : '',
        );

        $this->components->task($description, function () use ($event) {
            $this->dispatcher->dispatch(new ScheduledTaskStarting($event));

            $start = microtime(true);

            try {
                $event->run($this->app);

                $this->dispatcher->dispatch(
                    new ScheduledTaskFinished(
                        $event,
                        round(microtime(true) - $start, 2)
                    )
                );

                $this->eventsRan = true;
            } catch (Throwable $e) {
                $this->dispatcher->dispatch(new ScheduledTaskFailed($event, $e));

                $this->handler->report($e);
            }

            return $event->exitCode == 0;
        });

        if (!$event instanceof CallbackEvent) {
            $this->components->bulletList([
                $event->getSummaryForDisplay(),
            ]);
        }
    }

    /**
     * Run the given repeating events.
     *
     * @param \MacropaySolutions\Kernel\Support\Collection<\MacropaySolutions\Kernel\Console\Scheduling\Event> $events
     * @return void
     */
    protected function repeatEvents($events)
    {
        $hasEnteredMaintenanceMode = false;

        $endOfMinute = $this->startedAt->copy()->endOfMinute();

        while (\appDate()->now()->lte($endOfMinute)) {
            foreach ($events as $event) {
                if ($this->shouldInterrupt()) {
                    return;
                }

                if (!$event->shouldRepeatNow()) {
                    continue;
                }

                if (\appDate()->now()->gt($endOfMinute)) {
                    return;
                }

                $hasEnteredMaintenanceMode = $hasEnteredMaintenanceMode || $this->app->isDownForMaintenance();

                if ($hasEnteredMaintenanceMode && !$event->runsInMaintenanceMode()) {
                    continue;
                }

                if (!$event->filtersPass($this->app)) {
                    $this->dispatcher->dispatch(new ScheduledTaskSkipped($event));

                    continue;
                }

                if ($event->onOneServer) {
                    $this->runSingleServerEvent($event);
                } else {
                    $this->runEvent($event);
                }

                $this->eventsRan = true;
            }

            Sleep::usleep(100000);
        }
    }

    /**
     * Determine if the schedule run should be interrupted.
     *
     * @return bool
     */
    protected function shouldInterrupt()
    {
        return $this->cache->get('kernel:schedule:interrupt', false);
    }

    /**
     * Ensure the interrupt signal is cleared.
     *
     * @return void
     */
    protected function clearInterruptSignal()
    {
        $this->cache->forget('kernel:schedule:interrupt');
    }
}
