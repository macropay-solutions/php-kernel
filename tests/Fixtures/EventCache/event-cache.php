<?php

/**
 * Runs in its own PHP process: "compile" runs event:cache into <dir>/bootstrap/cache with a listen map,
 * "dispatch" boots a fresh app from that directory with an empty listen map and dispatches the event.
 * Usage: php event-cache.php <compile|dispatch> <dir>
 */

use MacropaySolutions\Framework\Application;
use MacropaySolutions\Framework\Console\Kernel as ConsoleKernel;
use MacropaySolutions\Framework\Exceptions\Handler;
use MacropaySolutions\Kernel\Contracts\Console\Kernel as ConsoleKernelContract;
use MacropaySolutions\Kernel\Contracts\Debug\ExceptionHandler;
use MacropaySolutions\Kernel\Tests\Fixtures\EventCache\ConfigurableEventProvider;
use MacropaySolutions\Kernel\Tests\Fixtures\Events\EventLog;
use MacropaySolutions\Kernel\Tests\Fixtures\Events\OrderShipped;
use MacropaySolutions\Kernel\Tests\Fixtures\Events\RecordShipment;

[, $step, $dir] = $argv;

require \dirname(__DIR__, 3) . '/vendor/autoload.php';

$app = new Application($dir);
$app->singleton(ExceptionHandler::class, Handler::class);
$app->singleton(ConsoleKernelContract::class, ConsoleKernel::class);

if ($step === 'compile') {
    ConfigurableEventProvider::$map = [OrderShipped::class => [RecordShipment::class]];
    $app->register(ConfigurableEventProvider::class);

    exit($app->make(ConsoleKernelContract::class)->call('event:cache'));
}

// The listen map is empty now: the listener can only come from the cache file.
ConfigurableEventProvider::$map = [];
$app->register(ConfigurableEventProvider::class);
$app->make('events')->dispatch(new OrderShipped(42));

echo \json_encode(['cached' => $app->eventsAreCached(), 'log' => EventLog::$entries]);
