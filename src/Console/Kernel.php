<?php

namespace MacropaySolutions\Framework\Console;

use Illuminate\Console\Application as ConsoleApp;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Console\Scheduling\ScheduleRunCommand;
use Illuminate\Contracts\Console\Kernel as KernelContract;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use MacropaySolutions\Framework\Application;
use MacropaySolutions\Framework\Exceptions\Handler;
use RuntimeException;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Throwable;

class Kernel implements KernelContract
{
    /**
     * The application implementation.
     *
     * @var \MacropaySolutions\Framework\Application
     */
    protected $app;

    /**
     * The Symfony event dispatcher implementation.
     *
     * @var \Symfony\Contracts\EventDispatcher\EventDispatcherInterface|null
     */
    protected $symfonyDispatcher;

    /**
     * The ConsoleApp application instance.
     *
     * @var \Illuminate\Console\Application
     */
    protected $consoleApp;

    /**
     * Indicates if facade aliases are enabled for the console.
     *
     * @var bool
     */
    protected $aliases = true;

    /**
     * The Run commands provided by the application.
     *
     * @var array
     */
    protected $commands = [];

    /**
     * Create a new console kernel instance.
     *
     * @param \MacropaySolutions\Framework\Application $app
     * @return void
     */
    public function __construct(Application $app)
    {
        $this->app = $app;

        if ($this->app->runningInConsole()) {
            $this->setRequestForConsole($this->app);
        } else {
            $this->rerouteSymfonyCommandEvents();
        }

        $this->app->prepareForConsoleCommand($this->aliases);
        $this->defineConsoleSchedule();
    }

    /**
     * Set the console application instance
     */
    public function setConsoleApp(\Illuminate\Console\Application|null $consoleApp): void
    {
        $this->consoleApp = $consoleApp;
    }

    /**
     * Set the request instance for URL generation.
     *
     * @param \Illuminate\Contracts\Foundation\Application $app
     * @return void
     */
    protected function setRequestForConsole(Application $app)
    {
        $uri = $app->make('config')->get('app.url', 'http://localhost');

        $components = parse_url($uri);

        $server = $_SERVER;

        if (isset($components['path'])) {
            $server = array_merge($server, [
                'SCRIPT_FILENAME' => $components['path'],
                'SCRIPT_NAME' => $components['path'],
            ]);
        }

        $app->instance(
            'request',
            Request::create(
                $uri,
                'GET',
                [],
                [],
                [],
                $server
            )
        );
    }

    /**
     * Re-route the Symfony command events to their Kernel counterparts.
     *
     * @return $this
     * @internal
     *
     */
    public function rerouteSymfonyCommandEvents()
    {
        if (is_null($this->symfonyDispatcher)) {
//            $this->symfonyDispatcher = new EventDispatcher;
            $this->symfonyDispatcher = \di(EventDispatcher::class);

            $this->symfonyDispatcher->addListener(ConsoleEvents::COMMAND, function (ConsoleCommandEvent $event) {
                $this->app[Dispatcher::class]->dispatch(
//                    new CommandStarting($event->getCommand()->getName(), $event->getInput(), $event->getOutput())
                    \di(
                        CommandStarting::class,
                        [$event->getCommand()->getName(), $event->getInput(), $event->getOutput()]
                    )
                );
            });

            $this->symfonyDispatcher->addListener(ConsoleEvents::TERMINATE, function (ConsoleTerminateEvent $event) {
                $this->app[Dispatcher::class]->dispatch(
                    //new CommandFinished(
                    //$event->getCommand()->getName(),
                    // $event->getInput(),
                    // $event->getOutput(),
                    // $event->getExitCode()
                    //)
                    \di(
                        CommandFinished::class,
                        [
                            $event->getCommand()->getName(),
                            $event->getInput(),
                            $event->getOutput(),
                            $event->getExitCode(),
                        ]
                    )
                );
            });
        }

        return $this;
    }

    /**
     * Define the application's command schedule.
     *
     * @return void
     */
    protected function defineConsoleSchedule()
    {
        $this->app->instance(
            Schedule::class,
            $schedule = new Schedule()
        );

        $this->schedule($schedule);
    }

    /**
     * Run the console application.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @return int
     */
    public function handle($input, $output = null)
    {
        try {
            $this->app->boot();

            $status = $this->getConsoleApp()->run($input, $output);
        } catch (Throwable $e) {
            $this->reportException($e);

            $this->renderException($output, $e);

            $status = 1;
        }

        $this->terminate($input, $status);

        return $status;
    }

    /**
     * Bootstrap the application for commands.
     *
     * @return void
     */
    public function bootstrap()
    {
        //
    }

    /**
     * Terminate the application.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param int $status
     * @return void
     */
    public function terminate($input, $status)
    {
        $this->app->terminate();
    }

    /**
     * Define the application's command schedule.
     *
     * @param \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        //
    }

    /**
     * Run an console command by name.
     *
     * @param string $command
     * @param array $parameters
     * @return int
     */
    public function call($command, array $parameters = [], $outputBuffer = null)
    {
        return $this->getConsoleApp()->call($command, $parameters, $outputBuffer);
    }

    /**
     * Queue the given console command.
     *
     * @param string $command
     * @param array $parameters
     * @return void
     */
    public function queue($command, array $parameters = [])
    {
        throw new RuntimeException('Queueing commands is not supported by Framework.');
    }

    /**
     * Get all the commands registered with the console.
     *
     * @return array
     */
    public function all()
    {
        return $this->getConsoleApp()->all();
    }

    /**
     * Get the output for the last run command.
     *
     * @return string
     */
    public function output()
    {
        return $this->getConsoleApp()->output();
    }

    /**
     * @see CommandsCacheCommand::handle()
     */
    public function getCommandMap(): array
    {
        return $this->getConsoleApp()->getCommandMap();
    }

    /**
     * Get the console application instance.
     *
     * @return \Illuminate\Console\Application
     */
    protected function getConsoleApp()
    {
        if (is_null($this->consoleApp)) {
//            $this->consoleApp = (new ConsoleApp($this->app, $this->app->make('events'), $this->app->version()))
            $this->consoleApp = \di(ConsoleApp::class, [$this->app, $this->app->make('events'), $this->app->version()]);

            if (!$this->app->commandsAreCached()) {
                $this->consoleApp->resolveCommands($this->getCommands());
            }

            $this->consoleApp->setContainerCommandLoader();

            if ($this->symfonyDispatcher instanceof EventDispatcher) {
                $this->consoleApp->setDispatcher($this->symfonyDispatcher);
                $this->consoleApp->setSignalsToDispatchEvent();
            }
        }

        return $this->consoleApp;
    }

    /**
     * Get the commands to add to the application.
     *
     * @return array
     */
    protected function getCommands()
    {
        return array_merge($this->commands, [
            ScheduleRunCommand::class,
        ]);
    }

    /**
     * Report the exception to the exception handler.
     *
     * @param \Throwable $e
     * @return void
     */
    protected function reportException(Throwable $e)
    {
        $this->resolveExceptionHandler()->report($e);
    }

    /**
     * Report the exception to the exception handler.
     *
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @param \Throwable $e
     * @return void
     */
    protected function renderException($output, Throwable $e)
    {
        $this->resolveExceptionHandler()->renderForConsole($output, $e);
    }

    /**
     * Get the exception handler from the container.
     *
     * @return \Illuminate\Contracts\Debug\ExceptionHandler
     */
    protected function resolveExceptionHandler()
    {
        if ($this->app->bound(ExceptionHandler::class)) {
            return $this->app->make(ExceptionHandler::class);
        } else {
            return $this->app->make(Handler::class);
        }
    }
}
