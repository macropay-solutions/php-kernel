<?php

namespace MacropaySolutions\Kernel\Tests;

use Carbon\Carbon;
use MacropaySolutions\Framework\Application;
use MacropaySolutions\Framework\Exceptions\Handler;
use MacropaySolutions\Framework\Console\Kernel as ConsoleKernel;
use MacropaySolutions\Kernel\Container\Container;
use MacropaySolutions\Kernel\Contracts\Console\Kernel as ConsoleKernelContract;
use MacropaySolutions\Kernel\Contracts\Debug\ExceptionHandler;
use MacropaySolutions\Kernel\Database\Connection;
use MacropaySolutions\Kernel\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Boots the real framework Application with an in-memory SQLite database,
 * the database queue and the database failed job provider.
 */
abstract class ApplicationTestCase extends BaseTestCase
{
    protected Application $app;

    private Carbon $testNow;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testNow = Carbon::now();

        $this->app = new Application(\dirname(__DIR__));
        $this->app->singleton(ExceptionHandler::class, Handler::class);
        $this->app->singleton(ConsoleKernelContract::class, ConsoleKernel::class);
        $this->app->configure('app');
        $this->app->configure('database');
        $this->app->configure('queue');
        $this->app->configure('cache');

        $config = $this->app->make('config');
        $config->set('app.key', 'base64:' . \base64_encode(\random_bytes(32)));
        $config->set('app.cipher', 'AES-256-CBC');
        $config->set('database.default', 'sqlite');
        $config->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        $config->set('queue.default', 'database');
        $config->set('queue.connections.database', [
            'driver' => 'database',
            'connection' => 'sqlite',
            'table' => 'jobs',
            'queue' => 'default',
            'retry_after' => 90,
        ]);
        $config->set('queue.failed', [
            'driver' => 'database-uuids',
            'database' => 'sqlite',
            'table' => 'failed_jobs',
        ]);
        $config->set('cache.default', 'array');

        $this->createQueueTables();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow($this->testNow);
        Container::setInstance(null);
        \restore_error_handler();
        \restore_exception_handler();

        parent::tearDown();
    }

    protected function db(): Connection
    {
        return $this->app->make('db')->connection();
    }

    /**
     * Run a console command the same way `php run <command>` does and return its exit code.
     */
    protected function artisan(string $command, array $parameters = []): int
    {
        return $this->app->make(ConsoleKernelContract::class)->call($command, $parameters);
    }

    protected function artisanOutput(): string
    {
        return $this->app->make(ConsoleKernelContract::class)->output();
    }

    protected function travelSeconds(int $seconds): void
    {
        Carbon::setTestNow(Carbon::now()->addSeconds($seconds));
    }

    private function createQueueTables(): void
    {
        $schema = $this->db()->getSchemaBuilder();

        $schema->create('jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        $schema->create('failed_jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
    }
}
