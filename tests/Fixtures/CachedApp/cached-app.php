<?php

/**
 * Runs an app skeleton at <dir> in its own PHP process, since cached files are read when the app is created.
 * "cache" runs the cache commands, "serve" boots the app and handles requests, "preregister" also registers
 * every available binding up front, as a pre-forking server's master process does.
 * Usage: php cached-app.php <cache|serve|preregister> <dir>
 */

use MacropaySolutions\Framework\Http\Request;
use MacropaySolutions\Kernel\Contracts\Console\Kernel as ConsoleKernelContract;

[, $step, $dir] = $argv;

$app = require $dir . '/bootstrap/app.php';

if ($step === 'cache') {
    $results = [];

    $commands = ['config:cache', 'route:cache', 'autowiring:cache', 'commands:cache', 'merge-cached-files:cache'];

    foreach ($commands as $command) {
        $results[$command] = $app->make(ConsoleKernelContract::class)->call($command);
    }

    echo \json_encode($results);

    exit(0);
}

if ($step === 'preregister') {
    $app->preRegisterAllAvailableBindings();
}

$responses = [];

foreach (['/status', '/items/5', '/items/abc'] as $uri) {
    $response = $app->handle(Request::create($uri, 'GET'));
    $responses[$uri] = [$response->getStatusCode(), $response->getStatusCode() === 200 ? $response->getContent() : ''];
}

echo \json_encode([
    'configCached' => $app->configurationIsCached(),
    'routesCached' => $app->routesAreCached(),
    'probe' => \config('probe.value'),
    'responses' => $responses,
    'bound' => [$app->bound('db'), $app->bound('cache'), $app->bound('queue')],
    'commandsCached' => $app->commandsAreCached(),
    'queueWorkListed' => \array_key_exists('queue:work', $app->make(ConsoleKernelContract::class)->all()),
]);
