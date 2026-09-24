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
use MacropaySolutions\Kernel\Console\MacroCacheCommand;
use MacropaySolutions\Kernel\Console\MacroClearCommand;
use MacropaySolutions\Kernel\Console\MergeCachedFilesCacheCommand;
use MacropaySolutions\Kernel\Console\MergeCachedFilesClearCommand;
use MacropaySolutions\Kernel\Console\Scheduling\ScheduleFinishCommand;
use MacropaySolutions\Kernel\Console\Scheduling\ScheduleRunCommand;
use MacropaySolutions\Kernel\Console\Scheduling\ScheduleWorkCommand;
use MacropaySolutions\Kernel\Console\ViewCacheCommand;
use MacropaySolutions\Kernel\Console\ViewClearCommand;
use MacropaySolutions\Kernel\Contracts\Support\DeferrableProvider;
use MacropaySolutions\Kernel\Database\Console\Migrations\FreshCommand as MigrateFreshCommand;
use MacropaySolutions\Kernel\Database\Console\Migrations\InstallCommand as MigrateInstallCommand;
use MacropaySolutions\Kernel\Database\Console\Migrations\MigrateCommand;
use MacropaySolutions\Kernel\Database\Console\Migrations\RefreshCommand as MigrateRefreshCommand;
use MacropaySolutions\Kernel\Database\Console\Migrations\ResetCommand as MigrateResetCommand;
use MacropaySolutions\Kernel\Database\Console\Migrations\RollbackCommand as MigrateRollbackCommand;
use MacropaySolutions\Kernel\Database\Console\Migrations\StatusCommand as MigrateStatusCommand;
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
use MacropaySolutions\KernelDev\Database\Console\Seeds\SeedCommand;
use MacropaySolutions\KernelDev\Database\Console\Seeds\SeederMakeCommand;
use MacropaySolutions\KernelDev\Database\Console\WipeCommand;
use MacropaySolutions\KernelDev\Foundation\Console\AboutCommand;
use MacropaySolutions\KernelDev\Queue\Console\BatchesTableCommand;
use MacropaySolutions\KernelDev\Queue\Console\FailedTableCommand;
use MacropaySolutions\KernelDev\Queue\Console\TableCommand;

class ConsoleServiceProvider extends ServiceProvider implements DeferrableProvider
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
        'MacroCache' => 'command.macro.cache',
        'MacroClear' => 'command.macro.clear',
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
        $this->app->singleton('command.autowiring.cache', [self::class, 'getAutowiringMethodsCacheCommand']);
    }

    public static function getAutowiringMethodsCacheCommand($app)
    {
        return new AutowiringMethodsCacheCommand($app->make('files'));
    }

    protected function registerAutowiringMethodsClearCommand(): void
    {
        $this->app->singleton('command.autowiring.clear', [self::class, 'getAutowiringMethodsClearCommand']);
    }

    public static function getAutowiringMethodsClearCommand($app)
    {
        return new AutowiringMethodsClearCommand($app->make('files'));
    }

    protected function registerMacroCacheCommand(): void
    {
        $this->app->singleton('command.macro.cache', [self::class, 'getMacroCacheCommand']);
    }

    public static function getMacroCacheCommand($app)
    {
        return new MacroCacheCommand($app->make('files'));
    }

    protected function registerMacroClearCommand(): void
    {
        $this->app->singleton('command.macro.clear', [self::class, 'getMacroClearCommand']);
    }

    public static function getMacroClearCommand($app)
    {
        return new MacroClearCommand($app->make('files'));
    }

    protected function registerMergeCachedFilesCacheCommand(): void
    {
        $this->app->singleton('command.merge-cached-files.cache', [self::class, 'getMergeCachedFilesCacheCommand']);
    }

    public static function getMergeCachedFilesCacheCommand($app)
    {
        return new MergeCachedFilesCacheCommand($app->make('files'));
    }

    protected function registerMergeCachedFilesClearCommand(): void
    {
        $this->app->singleton('command.merge-cached-files.clear', [self::class, 'getMergeCachedFilesClearCommand']);
    }

    public static function getMergeCachedFilesClearCommand($app)
    {
        return new MergeCachedFilesClearCommand($app->make('files'));
    }

    protected function registerCommandsCacheCommand(): void
    {
        $this->app->singleton('command.commands.cache', [self::class, 'getCommandsCacheCommand']);
    }

    public static function getCommandsCacheCommand($app)
    {
        return new CommandsCacheCommand($app->make('files'));
    }

    protected function registerCommandsClearCommand(): void
    {
        $this->app->singleton('command.commands.clear', [self::class, 'getCommandsClearCommand']);
    }

    public static function getCommandsClearCommand($app)
    {
        return new CommandsClearCommand($app->make('files'));
    }

    protected function registerEventCacheCommand(): void
    {
        $this->app->singleton('command.event.cache', [self::class, 'getEventCacheCommand']);
    }

    public static function getEventCacheCommand()
    {
        return new EventCacheCommand();
    }

    protected function registerEventClearCommand(): void
    {
        $this->app->singleton('command.event.clear', [self::class, 'getEventClearCommand']);
    }

    public static function getEventClearCommand($app)
    {
        return new EventClearCommand($app->make('files'));
    }

    protected function registerViewCacheCommand(): void
    {
        $this->app->singleton('command.view.cache', [self::class, 'getViewCacheCommand']);
    }

    public static function getViewCacheCommand($app)
    {
        if ($app->make('config')->has('view')) {
            $app->make('view');
        }

        return new ViewCacheCommand();
    }

    protected function registerViewClearCommand(): void
    {
        $this->app->singleton('command.view.clear', [self::class, 'getViewClearCommand']);
    }

    public static function getViewClearCommand($app)
    {
        if ($app->make('config')->has('view')) {
            $app->make('view');
        }

        return new ViewClearCommand($app->make('files'));
    }

    protected function registerCacheClearCommand(): void
    {
        $this->app->singleton('command.cache.clear', [self::class, 'getCacheClearCommand']);
    }

    public static function getCacheClearCommand($app)
    {
        return new CacheClearCommand($app->make('cache'), $app->make('files'));
    }

    protected function registerCacheForgetCommand()
    {
        $this->app->singleton('command.cache.forget', [self::class, 'getCacheForgetCommand']);
    }

    public static function getCacheForgetCommand($app)
    {
        return new CacheForgetCommand($app->make('cache'));
    }

    protected function registerCacheTableCommand()
    {
        $this->app->singleton('command.cache.table', [self::class, 'getCacheTableCommand']);
    }

    public static function getCacheTableCommand($app)
    {
        return new CacheTableCommand($app->make('files'), $app->make('composer'));
    }

    protected function registerClearResetsCommand()
    {
        $this->app->singleton('command.auth.resets.clear', [self::class, 'getClearResetsCommand']);
    }

    public static function getClearResetsCommand()
    {
        return new ClearResetsCommand();
    }

    protected function registerMigrateCommand()
    {
        $this->app->singleton('command.migrate', [self::class, 'getMigrateCommand']);
    }

    public static function getMigrateCommand($app)
    {
        return new MigrateCommand($app->make('migrator'), $app->make('events'));
    }

    protected function registerMigrateInstallCommand()
    {
        $this->app->singleton('command.migrate.install', [self::class, 'getMigrateInstallCommand']);
    }

    public static function getMigrateInstallCommand($app)
    {
        return new MigrateInstallCommand($app->make('migration.repository'));
    }

    protected function registerMigrateMakeCommand()
    {
        $this->app->singleton('command.migrate.make', [self::class, 'getMigrateMakeCommand']);
    }

    public static function getMigrateMakeCommand($app)
    {
        return new MigrateMakeCommand($app->make('migration.creator'), $app->make('composer'));
    }

    protected function registerMigrateFreshCommand(): void
    {
        $this->app->singleton('command.migrate.fresh', [self::class, 'getMigrateFreshCommand']);
    }

    public static function getMigrateFreshCommand()
    {
        return new MigrateFreshCommand();
    }

    protected function registerMigrateRefreshCommand()
    {
        $this->app->singleton('command.migrate.refresh', [self::class, 'getMigrateRefreshCommand']);
    }

    public static function getMigrateRefreshCommand()
    {
        return new MigrateRefreshCommand();
    }

    protected function registerMigrateResetCommand()
    {
        $this->app->singleton('command.migrate.reset', [self::class, 'getMigrateResetCommand']);
    }

    public static function getMigrateResetCommand($app)
    {
        return new MigrateResetCommand($app->make('migrator'));
    }

    protected function registerMigrateRollbackCommand()
    {
        $this->app->singleton('command.migrate.rollback', [self::class, 'getMigrateRollbackCommand']);
    }

    public static function getMigrateRollbackCommand($app)
    {
        return new MigrateRollbackCommand($app->make('migrator'));
    }

    protected function registerMigrateStatusCommand()
    {
        $this->app->singleton('command.migrate.status', [self::class, 'getMigrateStatusCommand']);
    }

    public static function getMigrateStatusCommand($app)
    {
        return new MigrateStatusCommand($app->make('migrator'));
    }

    protected function registerQueueClearCommand()
    {
        $this->app->singleton('command.queue.clear', [self::class, 'getQueueClearCommand']);
    }

    public static function getQueueClearCommand()
    {
        return new ClearQueueCommand();
    }

    protected function registerQueueFailedCommand()
    {
        $this->app->singleton('command.queue.failed', [self::class, 'getQueueFailedCommand']);
    }

    public static function getQueueFailedCommand()
    {
        return new ListFailedQueueCommand();
    }

    protected function registerQueueForgetCommand()
    {
        $this->app->singleton('command.queue.forget', [self::class, 'getQueueForgetCommand']);
    }

    public static function getQueueForgetCommand()
    {
        return new ForgetFailedQueueCommand();
    }

    protected function registerQueueFlushCommand()
    {
        $this->app->singleton('command.queue.flush', [self::class, 'getQueueFlushCommand']);
    }

    public static function getQueueFlushCommand()
    {
        return new FlushFailedQueueCommand();
    }

    protected function registerQueueListenCommand()
    {
        $this->app->singleton('command.queue.listen', [self::class, 'getQueueListenCommand']);
    }

    public static function getQueueListenCommand($app)
    {
        return new QueueListenCommand($app->make('queue.listener'));
    }

    protected function registerQueueRestartCommand()
    {
        $this->app->singleton('command.queue.restart', [self::class, 'getQueueRestartCommand']);
    }

    public static function getQueueRestartCommand($app)
    {
        return new QueueRestartCommand($app->make('cache.store'));
    }

    protected function registerQueueRetryCommand()
    {
        $this->app->singleton('command.queue.retry', [self::class, 'getQueueRetryCommand']);
    }

    public static function getQueueRetryCommand()
    {
        return new QueueRetryCommand();
    }

    protected function registerQueueWorkCommand()
    {
        $this->app->singleton('command.queue.work', [self::class, 'getQueueWorkCommand']);
    }

    public static function getQueueWorkCommand($app)
    {
        return new QueueWorkCommand($app->make('queue.worker'), $app->make('cache.store'));
    }

    protected function registerQueueFailJobCommand(): void
    {
        $this->app->singleton('command.queue.fail', [self::class, 'getQueueFailJobCommand']);
    }

    public static function getQueueFailJobCommand($app)
    {
        return new QueueFailJobCommand($app->make('queue.worker'), $app->make('cache.store'));
    }

    protected function registerQueueFailedTableCommand()
    {
        $this->app->singleton('command.queue.failed-table', [self::class, 'getQueueFailedTableCommand']);
    }

    public static function getQueueFailedTableCommand($app)
    {
        return new FailedTableCommand($app->make('files'), $app->make('composer'));
    }

    protected function registerQueueBatchesTableCommand()
    {
        $this->app->singleton('command.queue.batches-table', [self::class, 'getQueueBatchesTableCommand']);
    }

    public static function getQueueBatchesTableCommand($app)
    {
        return new BatchesTableCommand($app->make('files'), $app->make('composer'));
    }

    protected function registerQueueTableCommand()
    {
        $this->app->singleton('command.queue.table', [self::class, 'getQueueTableCommand']);
    }

    public static function getQueueTableCommand($app)
    {
        return new TableCommand($app->make('files'), $app->make('composer'));
    }

    protected function registerSeederMakeCommand()
    {
        $this->app->singleton('command.seeder.make', [self::class, 'getSeederMakeCommand']);
    }

    public static function getSeederMakeCommand($app)
    {
        return new SeederMakeCommand($app->make('files'));
    }

    protected function registerSeedCommand()
    {
        $this->app->singleton('command.seed', [self::class, 'getSeedCommand']);
    }

    public static function getSeedCommand($app)
    {
        return new SeedCommand($app->make('db'));
    }

    protected function registerWipeCommand()
    {
        $this->app->singleton('command.wipe', [self::class, 'getWipeCommand']);
    }

    public static function getWipeCommand()
    {
        return new WipeCommand();
    }

    protected function registerAboutCommand(): void
    {
        $this->app->singleton('command.about', [self::class, 'getAboutCommand']);
    }

    public static function getAboutCommand($app)
    {
        return new AboutCommand($app->make('composer'));
    }

    protected function registerScheduleFinishCommand()
    {
        $this->app->singleton('command.schedule.finish', [self::class, 'getScheduleFinishCommand']);
    }

    public static function getScheduleFinishCommand()
    {
        return new ScheduleFinishCommand();
    }

    protected function registerScheduleRunCommand()
    {
        $this->app->singleton('command.schedule.run', [self::class, 'getScheduleRunCommand']);
    }

    public static function getScheduleRunCommand()
    {
        return new ScheduleRunCommand();
    }

    protected function registerScheduleWorkCommand()
    {
        $this->app->singleton('command.schedule.work', [self::class, 'getScheduleWorkCommand']);
    }

    public static function getScheduleWorkCommand()
    {
        return new ScheduleWorkCommand();
    }

    protected function registerSchemaDumpCommand()
    {
        $this->app->singleton('command.schema.dump', [self::class, 'getSchemaDumpCommand']);
    }

    public static function getSchemaDumpCommand()
    {
        return new DumpCommand();
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
