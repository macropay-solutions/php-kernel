<?php

namespace MacropaySolutions\Kernel\Console;

use Closure;
use MacropaySolutions\Kernel\Console\Events\RunStarting;
use MacropaySolutions\Kernel\Contracts\Console\Application as ApplicationContract;
use MacropaySolutions\Kernel\Contracts\Container\Container;
use MacropaySolutions\Kernel\Contracts\Events\Dispatcher;
use MacropaySolutions\Kernel\Support\ProcessUtils;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Process\PhpExecutableFinder;

class Application extends SymfonyApplication implements ApplicationContract
{
    /**
     * The Kernel application instance.
     *
     * @var \MacropaySolutions\Kernel\Contracts\Container\Container
     */
    protected $app;

    /**
     * The event dispatcher instance.
     *
     * @var \MacropaySolutions\Kernel\Contracts\Events\Dispatcher
     */
    protected $events;

    /**
     * The output from the previous command.
     *
     * @var \Symfony\Component\Console\Output\BufferedOutput
     */
    protected $lastOutput;

    /**
     * The console application bootstrappers.
     *
     * @var array
     */
    protected static $bootstrappers = [];

    /**
     * A map of command names to classes.
     *
     * @var array
     */
    protected $commandMap = [];

    protected array $commandMapFqnFromCacheIndex = [];

    /**
     * Create a new console application.
     *
     * @param \MacropaySolutions\Kernel\Contracts\Container\Container $app
     * @param \MacropaySolutions\Kernel\Contracts\Events\Dispatcher $events
     * @param string $version
     * @return void
     */
    public function __construct(Container $app, Dispatcher $events, $version)
    {
        parent::__construct('PHP-Framework', $version);

        $this->app = $app;
        $this->events = $events;
        $this->setAutoExit(false);
        $this->setCatchExceptions(false);

        $this->events->dispatch(new RunStarting($this));

        if ($this->app->commandsAreCached()) {
            $this->commandMap = $this->app::getCachedFileContentsFromMemory($this->app::COMMANDS_PHP) ??
                require $this->app->getCachedCommandsPath();
            $this->commandMapFqnFromCacheIndex = \array_flip($this->commandMap);
        }

        $this->bootstrap();
    }

    public function getCommandMap(): array
    {
        return $this->commandMap;
    }

    /**
     * Determine the proper PHP executable.
     */
    public static function phpBinary(): string
    {
        return ProcessUtils::escapeArgument((new PhpExecutableFinder())->find(false));
    }

    /**
     * Determine the proper run executable.
     */
    public static function runBinary(): string
    {
        return ProcessUtils::escapeArgument( 'run');
    }

    /**
     * Format the given command as a fully-qualified executable command.
     *
     * @param string $string
     * @return string
     */
    public static function formatCommandString($string)
    {
        return sprintf('%s %s %s', static::phpBinary(), static::runBinary(), $string);
    }

    /**
     * Register a console "starting" bootstrapper.
     *
     * @param \Closure $callback
     * @return void
     */
    public static function starting(Closure $callback)
    {
        static::$bootstrappers[] = $callback;
    }

    /**
     * Bootstrap the console application.
     *
     * @return void
     */
    protected function bootstrap()
    {
        foreach (static::$bootstrappers as $bootstrapper) {
            $bootstrapper($this);
        }
    }

    /**
     * Clear the console application bootstrappers.
     *
     * @return void
     */
    public static function forgetBootstrappers()
    {
        static::$bootstrappers = [];
    }

    /**
     * Run an console command by name.
     *
     * @param string $command
     * @param array $parameters
     * @param \Symfony\Component\Console\Output\OutputInterface|null $outputBuffer
     * @return int
     *
     * @throws \Symfony\Component\Console\Exception\CommandNotFoundException
     */
    public function call($command, array $parameters = [], $outputBuffer = null)
    {
        [$command, $input] = $this->parseCommand($command, $parameters);

        if (!$this->has($command)) {
            throw new CommandNotFoundException(sprintf('The command "%s" does not exist.', $command));
        }

        return $this->run(
            $input,
            $this->lastOutput = $outputBuffer ?: new BufferedOutput()
        );
    }

    /**
     * Parse the incoming command and its input.
     *
     * @param string $command
     * @param array $parameters
     * @return array
     */
    protected function parseCommand($command, $parameters)
    {
        if (is_subclass_of($command, SymfonyCommand::class)) {
            $callingClass = true;

            $command = $this->app->make($command)->getName();
        }

        if (!isset($callingClass) && empty($parameters)) {
            $command = $this->getCommandName($input = new StringInput($command));
        } else {
            array_unshift($parameters, $command);

            $input = new ArrayInput($parameters);
        }

        return [$command, $input];
    }

    /**
     * Get the output for the last run command.
     *
     * @return string
     */
    public function output()
    {
        return $this->lastOutput && method_exists($this->lastOutput, 'fetch')
            ? $this->lastOutput->fetch()
            : '';
    }

    /**
     * Add a command to the console.
     */
    public function add(SymfonyCommand $command): ?SymfonyCommand
    {
        return $this->addCommand($command);
    }

    /**
     * Add a command to the console.
     */
    public function addCommand(callable|SymfonyCommand $command): ?SymfonyCommand
    {
        if ($command instanceof Command) {
            $command->setApp($this->app);
        }

        return $this->addToParent($command);
    }

    /**
     * Add the command to the parent instance.
     */
    protected function addToParent(callable|SymfonyCommand $command): ?SymfonyCommand
    {
        return parent::addCommand($command);
    }

    /**
     * Add a command, resolving through the application.
     *
     * @param \MacropaySolutions\Kernel\Console\Command|string $command
     * @return \Symfony\Component\Console\Command\Command|null
     */
    public function resolve($command)
    {
        if (\is_subclass_of($command, SymfonyCommand::class)) {
            if (isset($this->commandMapFqnFromCacheIndex[\is_object($command) ? $command::class : $command])) {
                return null;
            }

            if ($command instanceof Command) {
                if (
                    ($commandName = $command->getName())
                    || ($commandName = $command::getDefaultName())
                ) {
                    foreach (explode('|', $commandName) as $name) {
                        $this->commandMap[$name] = $command;
                    }

                    return null;
                }

                return $this->add($command);
            }

            if (
                ($commandName = $command::getDefaultName())
                || ($commandName = $this->app->make($command)->getName())
            ) {
                foreach (explode('|', $commandName) as $name) {
                    $this->commandMap[$name] = $command;
                }

                return null;
            }
        }

        if ($command instanceof Command) {
            return $this->add($command);
        }

        // Framework uses strings for builtin commands like 'command.commands.cache'
        $commandInstance = $this->app->make($command);

        if ($commandInstance instanceof Command) {
            if (
                ($commandName = $commandInstance->getName())
                || ($commandName = $commandInstance::getDefaultName())
            ) {
                foreach (explode('|', $commandName) as $name) {
                    $this->commandMap[$name] = $command;
                }

                return null;
            }
        }

        return $this->add($commandInstance);
    }

    /**
     * Resolve an array of commands through the application.
     *
     * @param array|mixed $commands
     * @return $this
     */
    public function resolveCommands($commands)
    {
        $commands = is_array($commands) ? $commands : func_get_args();

        foreach ($commands as $command) {
            $this->resolve($command);
        }

        return $this;
    }

    /**
     * Set the container command loader for lazy resolution.
     *
     * @return $this
     */
    public function setContainerCommandLoader()
    {
        $this->setCommandLoader(new ContainerCommandLoader($this->app, $this->commandMap));

        return $this;
    }

    /**
     * Get the default input definition for the application.
     *
     * This is used to add the --env option to every available command.
     *
     * @return \Symfony\Component\Console\Input\InputDefinition
     */
    protected function getDefaultInputDefinition(): InputDefinition
    {
        return tap(parent::getDefaultInputDefinition(), function ($definition) {
            $definition->addOption($this->getEnvironmentOption());
        });
    }

    /**
     * Get the global environment option for the definition.
     *
     * @return \Symfony\Component\Console\Input\InputOption
     */
    protected function getEnvironmentOption()
    {
        $message = 'The environment the command should run under';

        return new InputOption('--env', null, InputOption::VALUE_OPTIONAL, $message);
    }

    /**
     * Get the Kernel application instance.
     *
     * @return \MacropaySolutions\Kernel\Contracts\Foundation\Application
     */
    public function getApp()
    {
        return $this->app;
    }
}
