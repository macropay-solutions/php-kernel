<?php

namespace MacropaySolutions\Framework\Console;

use MacropaySolutions\Kernel\Auth\Console\ClearResetsCommand;
use MacropaySolutions\Kernel\Cache\Console\ClearCommand as CacheClearCommand;
use MacropaySolutions\Kernel\Cache\Console\ForgetCommand as CacheForgetCommand;
use MacropaySolutions\Kernel\Console\AutowiringMethodsCacheCommand;
use MacropaySolutions\Kernel\Console\AutowiringMethodsClearCommand;
use MacropaySolutions\Kernel\Console\CommandsCacheCommand;
use MacropaySolutions\Kernel\Console\CommandsClearCommand;
use MacropaySolutions\Kernel\Console\EventCacheCommand;
use MacropaySolutions\Kernel\Console\EventClearCommand;
use MacropaySolutions\Kernel\Console\MergeCachedFilesCacheCommand;
use MacropaySolutions\Kernel\Console\MergeCachedFilesClearCommand;
use MacropaySolutions\Kernel\Console\Scheduling\ScheduleFinishCommand;
use MacropaySolutions\Kernel\Console\Scheduling\ScheduleRunCommand;
use MacropaySolutions\Kernel\Console\Scheduling\ScheduleWorkCommand;
use MacropaySolutions\Kernel\Console\ViewCacheCommand;
use MacropaySolutions\Kernel\Console\ViewClearCommand;
use MacropaySolutions\Kernel\Database\Console\Migrations\FreshCommand as MigrateFreshCommand;
use MacropaySolutions\Kernel\Database\Console\Migrations\InstallCommand as MigrateInstallCommand;
use MacropaySolutions\Kernel\Database\Console\Migrations\MigrateCommand;
use MacropaySolutions\Kernel\Database\Console\Migrations\RefreshCommand as MigrateRefreshCommand;
use MacropaySolutions\Kernel\Database\Console\Migrations\ResetCommand as MigrateResetCommand;
use MacropaySolutions\Kernel\Database\Console\Migrations\RollbackCommand as MigrateRollbackCommand;
use MacropaySolutions\Kernel\Database\Console\Migrations\StatusCommand as MigrateStatusCommand;
use MacropaySolutions\Kernel\Database\Console\Seeds\SeedCommand;
use MacropaySolutions\Kernel\Queue\Console\ClearCommand as ClearQueueCommand;
use MacropaySolutions\Kernel\Queue\Console\FailJobCommand as QueueFailJobCommand;
use MacropaySolutions\Kernel\Queue\Console\FlushFailedCommand as FlushFailedQueueCommand;
use MacropaySolutions\Kernel\Queue\Console\ForgetFailedCommand as ForgetFailedQueueCommand;
use MacropaySolutions\Kernel\Queue\Console\ListenCommand as QueueListenCommand;
use MacropaySolutions\Kernel\Queue\Console\ListFailedCommand as ListFailedQueueCommand;
use MacropaySolutions\Kernel\Queue\Console\RestartCommand as QueueRestartCommand;
use MacropaySolutions\Kernel\Queue\Console\RetryCommand as QueueRetryCommand;
use MacropaySolutions\Kernel\Queue\Console\WorkCommand as QueueWorkCommand;
use MacropaySolutions\Kernel\Support\ServiceProvider;
use MacropaySolutions\KernelDev\Cache\Console\CacheTableCommand;
use MacropaySolutions\KernelDev\Database\Console\DumpCommand;
use MacropaySolutions\KernelDev\Database\Console\Migrations\MigrateMakeCommand;
use MacropaySolutions\KernelDev\Database\Console\Seeds\SeederMakeCommand;
use MacropaySolutions\KernelDev\Database\Console\WipeCommand;
use MacropaySolutions\KernelDev\Foundation\Console\AboutCommand;
use MacropaySolutions\KernelDev\Queue\Console\BatchesTableCommand;
use MacropaySolutions\KernelDev\Queue\Console\FailedTableCommand;
use MacropaySolutions\KernelDev\Queue\Console\TableCommand;

class ConsoleServiceProvider extends ServiceProvider
{
    /**
     * The commands to be registered.
     *
     * @var array
     */
    protected $commands = [
        'AutowiringMethodsCache' => 'command.autowiring.cache',
        'AutowiringMethodsClear' => 'command.autowiring.clear',
        'EventCache' => 'command.event.cache',
        'EventClear' => 'command.event.clear',
        'CacheClear' => 'command.cache.clear',
        'CacheForget' => 'command.cache.forget',
        'CommandsCache' => 'command.commands.cache',
        'CommandsClear' => 'command.commands.clear',
        'ClearResets' => 'command.auth.resets.clear',
        'MergeCachedFilesCache' => 'command.merge-cached-files.cache',
        'MergeCachedFilesClear' => 'command.merge-cached-files.clear',
        'Migrate' => 'command.migrate',
        'MigrateInstall' => 'command.migrate.install',
        'MigrateRollback' => 'command.migrate.rollback',
        'MigrateStatus' => 'command.migrate.status',
        'QueueClear' => 'command.queue.clear',
        'QueueFailed' => 'command.queue.failed',
        'QueueFlush' => 'command.queue.flush',
        'QueueForget' => 'command.queue.forget',
        'QueueListen' => 'command.queue.listen',
        'QueueRestart' => 'command.queue.restart',
        'QueueRetry' => 'command.queue.retry',
        'QueueWork' => 'command.queue.work',
        'QueueFailJob' => 'command.queue.fail',
        'ScheduleFinish' => 'command.schedule.finish',
        'ScheduleRun' => 'command.schedule.run',
        'ScheduleWork' => 'command.schedule.work',
        'ViewCache' => 'command.view.cache',
        'ViewClear' => 'command.view.clear',
    ];

    /**
     * The dev commands to be registered.
     *
     * @var array
     */
    protected $devCommands = [
        'About' => 'command.about',
        'Wipe' => 'command.wipe',
        'SchemaDump' => 'command.schema.dump',
        'CacheTable' => 'command.cache.table',
        'MigrateMake' => 'command.migrate.make',
        'MigrateFresh' => 'command.migrate.fresh',
        'MigrateRefresh' => 'command.migrate.refresh',
        'MigrateReset' => 'command.migrate.reset',
        'QueueFailedTable' => 'command.queue.failed-table',
        'QueueBatchesTable' => 'command.queue.batches-table',
        'QueueTable' => 'command.queue.table',
        'Seed' => 'command.seed',
        'SeederMake' => 'command.seeder.make',
    ];

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerCommands(
            $this->app::isDevEnv() ? \array_merge(
                $this->commands,
                $this->devCommands
            ) : $this->commands
        );
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

    protected function registerAutowiringMethodsCacheCommand(): void
    {
        $this->app->singleton('command.autowiring.cache', function ($app): AutowiringMethodsCacheCommand {
            return new AutowiringMethodsCacheCommand($app['files']);
        });
    }

    protected function registerAutowiringMethodsClearCommand(): void
    {
        $this->app->singleton('command.autowiring.clear', function ($app): AutowiringMethodsClearCommand {
            return new AutowiringMethodsClearCommand($app['files']);
        });
    }

    protected function registerMergeCachedFilesCacheCommand(): void
    {
        $this->app->singleton('command.merge-cached-files.cache', function ($app): MergeCachedFilesCacheCommand {
            return new MergeCachedFilesCacheCommand($app['files']);
        });
    }

    protected function registerMergeCachedFilesClearCommand(): void
    {
        $this->app->singleton('command.merge-cached-files.clear', function ($app): MergeCachedFilesClearCommand {
            return new MergeCachedFilesClearCommand($app['files']);
        });
    }

    protected function registerCommandsCacheCommand(): void
    {
        $this->app->singleton('command.commands.cache', function ($app): CommandsCacheCommand {
            return new CommandsCacheCommand($app['files']);
        });
    }

    protected function registerCommandsClearCommand(): void
    {
        $this->app->singleton('command.commands.clear', function ($app): CommandsClearCommand {
            return new CommandsClearCommand($app['files']);
        });
    }

    protected function registerEventCacheCommand(): void
    {
        $this->app->singleton('command.event.cache', function (): EventCacheCommand {
            return new EventCacheCommand();
        });
    }

    protected function registerEventClearCommand(): void
    {
        $this->app->singleton('command.event.clear', function ($app): EventClearCommand {
            return new EventClearCommand($app['files']);
        });
    }

    protected function registerViewCacheCommand(): void
    {
        $this->app->singleton('command.view.cache', function ($app): ViewCacheCommand {
            if ($app->make('config')->has('view')) {
                $app->make('view');
            }

            return new ViewCacheCommand();
        });
    }

    protected function registerViewClearCommand(): void
    {
        $this->app->singleton('command.view.clear', function ($app): ViewClearCommand {
            if ($app->make('config')->has('view')) {
                $app->make('view');
            }

            return new ViewClearCommand($app['files']);
        });
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerCacheClearCommand()
    {
        $this->app->singleton('command.cache.clear', function ($app): CacheClearCommand {
            return new CacheClearCommand($app['cache'], $app['files']);
        });
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerCacheForgetCommand()
    {
        $this->app->singleton('command.cache.forget', function ($app): CacheForgetCommand {
            return new CacheForgetCommand($app['cache']);
        });
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerCacheTableCommand()
    {
        $this->app->singleton('command.cache.table', function ($app): CacheTableCommand {
            return new CacheTableCommand($app['files'], $app['composer']);
        });
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerClearResetsCommand()
    {
        $this->app->singleton('command.auth.resets.clear', function (): ClearResetsCommand {
            return new ClearResetsCommand();
        });
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerMigrateCommand()
    {
        $this->app->singleton('command.migrate', function ($app): MigrateCommand {
            return new MigrateCommand($app['migrator'], $app['events']);
        });
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerMigrateInstallCommand()
    {
        $this->app->singleton('command.migrate.install', function ($app): MigrateInstallCommand {
            return new MigrateInstallCommand($app['migration.repository']);
        });
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerMigrateMakeCommand()
    {
        $this->app->singleton('command.migrate.make', function ($app): MigrateMakeCommand {
            // Once we have the migration creator registered, we will create the command
            // and inject the creator. The creator is responsible for the actual file
            // creation of the migrations, and may be extended by these developers.
            $creator = $app['migration.creator'];

            $composer = $app['composer'];

            return new MigrateMakeCommand($creator, $composer);
        });
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerMigrateFreshCommand()
    {
        $this->app->singleton('command.migrate.fresh', function (): MigrateFreshCommand {
            return new MigrateFreshCommand();
        });
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerMigrateRefreshCommand()
    {
        $this->app->singleton('command.migrate.refresh', function (): MigrateRefreshCommand {
            return new MigrateRefreshCommand();
        });
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerMigrateResetCommand()
    {
        $this->app->singleton('command.migrate.reset', function ($app): MigrateResetCommand {
            return new MigrateResetCommand($app['migrator']);
        });
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerMigrateRollbackCommand()
    {
        $this->app->singleton('command.migrate.rollback', function ($app): MigrateRollbackCommand {
            return new MigrateRollbackCommand($app['migrator']);
        });
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerMigrateStatusCommand()
    {
        $this->app->singleton('command.migrate.status', function ($app): MigrateStatusCommand {
            return new MigrateStatusCommand($app['migrator']);
        });
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerQueueClearCommand()
    {
        $this->app->singleton('command.queue.clear', function (): ClearQueueCommand {
            return new ClearQueueCommand();
        });
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerQueueFailedCommand()
    {
        $this->app->singleton('command.queue.failed', function (): ListFailedQueueCommand {
            return new ListFailedQueueCommand();
        });
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerQueueForgetCommand()
    {
        $this->app->singleton('command.queue.forget', function (): ForgetFailedQueueCommand {
            return new ForgetFailedQueueCommand();
        });
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerQueueFlushCommand()
    {
        $this->app->singleton('command.queue.flush', function (): FlushFailedQueueCommand {
            return new FlushFailedQueueCommand();
        });
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerQueueListenCommand()
    {
        $this->app->singleton('command.queue.listen', function ($app): QueueListenCommand {
            return new QueueListenCommand($app['queue.listener']);
        });
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerQueueRestartCommand()
    {
        $this->app->singleton('command.queue.restart', function ($app): QueueRestartCommand {
            return new QueueRestartCommand($app['cache.store']);
        });
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerQueueRetryCommand()
    {
        $this->app->singleton('command.queue.retry', function (): QueueRetryCommand {
            return new QueueRetryCommand();
        });
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerQueueWorkCommand()
    {
        $this->app->singleton('command.queue.work', function ($app): QueueWorkCommand {
            return new QueueWorkCommand($app['queue.worker'], $app['cache.store']);
        });
    }

    /**
     * Register the command.
     */
    protected function registerQueueFailJobCommand(): void
    {
        $this->app->singleton('command.queue.fail', function ($app): QueueFailJobCommand {
            return new QueueFailJobCommand($app['queue.worker'], $app['cache.store']);
        });
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerQueueFailedTableCommand()
    {
        $this->app->singleton('command.queue.failed-table', function ($app): FailedTableCommand {
            return new FailedTableCommand($app['files'], $app['composer']);
        });
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerQueueBatchesTableCommand()
    {
        $this->app->singleton('command.queue.batches-table', function ($app): BatchesTableCommand {
            return new BatchesTableCommand($app['files'], $app['composer']);
        });
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerQueueTableCommand()
    {
        $this->app->singleton('command.queue.table', function ($app): TableCommand {
            return new TableCommand($app['files'], $app['composer']);
        });
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerSeederMakeCommand()
    {
        $this->app->singleton('command.seeder.make', function ($app): SeederMakeCommand {
            return new SeederMakeCommand($app['files']);
        });
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerSeedCommand()
    {
        $this->app->singleton('command.seed', function ($app): SeedCommand {
            return new SeedCommand($app['db']);
        });
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerWipeCommand()
    {
        $this->app->singleton('command.wipe', function ($app): WipeCommand {
            return new WipeCommand();
        });
    }

    /**
     * Register the command.
     */
    protected function registerAboutCommand(): void
    {
        $this->app->singleton('command.about', function ($app): AboutCommand {
            return new AboutCommand($app->make('composer'));
        });
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerScheduleFinishCommand()
    {
        $this->app->singleton('command.schedule.finish', function (): ScheduleFinishCommand {
            return new ScheduleFinishCommand();
        });
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerScheduleRunCommand()
    {
        $this->app->singleton('command.schedule.run', function (): ScheduleRunCommand {
            return new ScheduleRunCommand();
        });
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerScheduleWorkCommand()
    {
        $this->app->singleton('command.schedule.work', function (): ScheduleWorkCommand {
            return new ScheduleWorkCommand();
        });
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerSchemaDumpCommand()
    {
        $this->app->singleton('command.schema.dump', function (): DumpCommand {
            return new DumpCommand();
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        if ($this->app::isDevEnv()) {
            return \array_merge(\array_values($this->commands), \array_values($this->devCommands));
        }

        return \array_values($this->commands);
    }
}
