<?php

namespace MacropaySolutions\Kernel\Database;

use MacropaySolutions\Kernel\Contracts\Events\Dispatcher;
use MacropaySolutions\Kernel\Contracts\Support\DeferrableProvider;
use MacropaySolutions\Kernel\Database\Console\Migrations\FreshCommand;
use MacropaySolutions\Kernel\Database\Console\Migrations\InstallCommand;
use MacropaySolutions\Kernel\Database\Console\Migrations\MigrateCommand;
use MacropaySolutions\Kernel\Database\Console\Migrations\RefreshCommand;
use MacropaySolutions\Kernel\Database\Console\Migrations\ResetCommand;
use MacropaySolutions\Kernel\Database\Console\Migrations\RollbackCommand;
use MacropaySolutions\Kernel\Database\Console\Migrations\StatusCommand;
use MacropaySolutions\Kernel\Database\Migrations\DatabaseMigrationRepository;
use MacropaySolutions\Kernel\Database\Migrations\Migrator;
use MacropaySolutions\Kernel\Support\ServiceProvider;
use MacropaySolutions\KernelDev\Database\Console\Migrations\MigrateMakeCommand;
use MacropaySolutions\KernelDev\Database\Migrations\MigrationCreator;

class MigrationServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * The commands to be registered.
     *
     * @var array
     */
    protected $commands = [
        'Migrate' => MigrateCommand::class,
        'MigrateInstall' => InstallCommand::class,
        'MigrateRollback' => RollbackCommand::class,
        'MigrateStatus' => StatusCommand::class,
    ];

    /**
     * The dev commands to be registered.
     */
    protected array $devCommands = [
        'MigrateMake' => MigrateMakeCommand::class,
        'MigrateFresh' => FreshCommand::class,
        'MigrateRefresh' => RefreshCommand::class,
        'MigrateReset' => ResetCommand::class,
    ];

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerRepository();

        $this->registerMigrator();

        if ($this->app::isDevEnv()) {
            $this->registerCreator();
            $this->registerCommands($this->commands + $this->devCommands);

            return;
        }

        $this->registerCommands($this->commands);
    }

    /**
     * Register the migration repository service.
     *
     * @return void
     */
    protected function registerRepository()
    {
        $this->app->singleton('migration.repository', [self::class, 'getMigrationRepository']);
    }

    public static function getMigrationRepository($app)
    {
        return new DatabaseMigrationRepository($app->make('db'), $app->make('config')->get('database.migrations'));
    }

    /**
     * Register the migrator service.
     *
     * @return void
     */
    protected function registerMigrator()
    {
        // The migrator is responsible for actually running and rollback the migration
        // files in the application. We'll pass in our database connection resolver
        // so the migrator can resolve any of these connections when it needs to.
        $this->app->singleton('migrator', [self::class, 'getMigrator']);
    }

    public static function getMigrator($app)
    {
        return new Migrator(
            $app->make('migration.repository'),
            $app->make('db'),
            $app->make('files'),
            $app->make('events')
        );
    }

    /**
     * Register the migration creator.
     *
     * @return void
     */
    protected function registerCreator()
    {
        $this->app->singleton('migration.creator', [self::class, 'getMigrationCreator']);
    }

    public static function getMigrationCreator($app)
    {
        return new MigrationCreator($app->make('files'), $app->basePath('stubs'));
    }

    /**
     * Register the given commands.
     *
     * @param array $commands
     * @return void
     */
    protected function registerCommands(array $commands)
    {
        foreach (array_keys($commands) as $command) {
            $this->{"register{$command}Command"}();
        }

        if (!$this->app->commandsAreCached()) {
            $this->commands(array_values($commands));
        }
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerMigrateCommand()
    {
        $this->app->singleton(MigrateCommand::class, [self::class, 'getMigrateCommand']);
    }

    public static function getMigrateCommand($app)
    {
        return new MigrateCommand($app->make('migrator'), $app->make(Dispatcher::class));
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerMigrateFreshCommand()
    {
        $this->app->singleton(FreshCommand::class);
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerMigrateInstallCommand()
    {
        $this->app->singleton(InstallCommand::class, [self::class, 'getMigrateInstallCommand']);
    }

    public static function getMigrateInstallCommand($app)
    {
        return new InstallCommand($app->make('migration.repository'));
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerMigrateMakeCommand()
    {
        $this->app->singleton(MigrateMakeCommand::class, [self::class, 'getMigrateMakeCommand']);
    }

    public static function getMigrateMakeCommand($app)
    {
        // Once we have the migration creator registered, we will create the command
        // and inject the creator. The creator is responsible for the actual file
        // creation of the migrations, and may be extended by these developers.
        return new MigrateMakeCommand($app->make('migration.creator'), $app->make('composer'));
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerMigrateRefreshCommand()
    {
        $this->app->singleton(RefreshCommand::class);
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerMigrateResetCommand()
    {
        $this->app->singleton(ResetCommand::class, [self::class, 'getMigrateResetCommand']);
    }

    public static function getMigrateResetCommand($app)
    {
        return new ResetCommand($app->make('migrator'));
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerMigrateRollbackCommand()
    {
        $this->app->singleton(RollbackCommand::class, [self::class, 'getMigrateRollbackCommand']);
    }

    public static function getMigrateRollbackCommand($app)
    {
        return new RollbackCommand($app->make('migrator'));
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerMigrateStatusCommand()
    {
        $this->app->singleton(StatusCommand::class, [self::class, 'getMigrateStatusCommand']);
    }

    public static function getMigrateStatusCommand($app)
    {
        return new StatusCommand($app->make('migrator'));
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        if ($this->app::isDevEnv()) {
            return \array_merge([
                'migrator',
                'migration.repository',
                'migration.creator',
            ], \array_values($this->commands + $this->devCommands));
        }

        return \array_merge([
            'migrator',
            'migration.repository',
        ], \array_values($this->commands));
    }
}
