<?php

/**
 * Runs in its own PHP process: "compile" registers deferred macros and runs macro:cache into <dir>/bootstrap,
 * "call" loads the compiled traits from there (as an app's autoloader would) and calls the macros natively.
 * Usage: php macro-cache.php <compile|call> <dir>
 */

use MacropaySolutions\Framework\Application;
use MacropaySolutions\Framework\Console\Kernel as ConsoleKernel;
use MacropaySolutions\Framework\Exceptions\Handler;
use MacropaySolutions\Kernel\Contracts\Console\Kernel as ConsoleKernelContract;
use MacropaySolutions\Kernel\Contracts\Debug\ExceptionHandler;
use MacropaySolutions\Kernel\Support\Collection;
use MacropaySolutions\Kernel\Tests\Fixtures\Macros\CollectionMacros;

[, $step, $dir] = $argv;

require \dirname(__DIR__, 3) . '/vendor/autoload.php';

if ($step === 'compile') {
    $app = new Application($dir);
    $app->singleton(ExceptionHandler::class, Handler::class);
    $app->singleton(ConsoleKernelContract::class, ConsoleKernel::class);

    Collection::deferredMacro('doubled', [CollectionMacros::class, 'doubled']);
    Collection::deferredMacro('fromRange', [CollectionMacros::class, 'fromRange']);

    exit($app->make(ConsoleKernelContract::class)->call('macro:cache'));
}

\spl_autoload_register(static function (string $class) use ($dir): void {
    $prefix = 'MacropaySolutions\\Framework\\Traitables\\';

    if (!\str_starts_with($class, $prefix)) {
        return;
    }

    $file = $dir . '/bootstrap/cache/traitables/' . \substr($class, \strlen($prefix)) . '.php';

    if (\is_file($file)) {
        require $file;
    }
}, true, true);

new Application($dir);

echo \json_encode([
    'native' => \method_exists(Collection::class, 'doubled') && \method_exists(Collection::class, 'fromRange'),
    'doubled' => (new Collection([1, 2, 3]))->doubled(3)->all(),
    'fromRange' => Collection::fromRange(2, 4)->all(),
    'hasMacro' => Collection::hasMacro('doubled'),
]);
