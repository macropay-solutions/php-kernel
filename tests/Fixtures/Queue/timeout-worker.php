<?php

/**
 * Runs in its own PHP process, because a timed-out worker kills its own process: dispatches a job that sleeps
 * past its 1 second timeout into an SQLite database queue at <dir>/db.sqlite and runs queue:work once.
 * Usage: php timeout-worker.php <dir> <sleep-seconds>
 */

use MacropaySolutions\Framework\Application;
use MacropaySolutions\Framework\Console\Kernel as ConsoleKernel;
use MacropaySolutions\Framework\Exceptions\Handler;
use MacropaySolutions\Kernel\Contracts\Console\Kernel as ConsoleKernelContract;
use MacropaySolutions\Kernel\Contracts\Debug\ExceptionHandler;
use MacropaySolutions\Kernel\Database\Schema\Blueprint;
use MacropaySolutions\Kernel\Tests\Fixtures\Queue\SleepingJob;

[, $dir, $seconds] = $argv;

require \dirname(__DIR__, 3) . '/vendor/autoload.php';

\touch($dir . '/db.sqlite');

$app = new Application(\dirname(__DIR__, 3));
$app->singleton(ExceptionHandler::class, Handler::class);
$app->singleton(ConsoleKernelContract::class, ConsoleKernel::class);
$app->configure('app');
$app->configure('database');
$app->configure('queue');
$config = $app->make('config');
$config->set('app.key', 'base64:' . \base64_encode(\random_bytes(32)));
$config->set('app.cipher', 'AES-256-CBC');
$config->set('database.default', 'sqlite');
$config->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => $dir . '/db.sqlite', 'prefix' => '']);
$config->set('queue.default', 'database');
$config->set('queue.connections.database', [
    'driver' => 'database',
    'connection' => 'sqlite',
    'table' => 'jobs',
    'queue' => 'default',
    'retry_after' => 90,
]);
$config->set('queue.failed', ['driver' => 'database-uuids', 'database' => 'sqlite', 'table' => 'failed_jobs']);
$config->set('cache.default', 'array');

$schema = $app->make('db')->connection()->getSchemaBuilder();
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

SleepingJob::new(['seconds' => (int)$seconds])->dispatch();

exit($app->make(ConsoleKernelContract::class)->call('queue:work', ['connection' => 'database', '--sleep' => 0]));
