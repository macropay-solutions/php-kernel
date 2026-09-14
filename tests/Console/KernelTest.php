<?php

use MacropaySolutions\Kernel\Console\Events\CommandFinished;
use MacropaySolutions\Kernel\Console\Events\CommandStarting;
use MacropaySolutions\Kernel\Contracts\Console\Kernel as ConsoleKernelContract;
use MacropaySolutions\Kernel\Contracts\Debug\ExceptionHandler as ExceptionHandlerContract;
use MacropaySolutions\Framework\Application;
use MacropaySolutions\Framework\Console\Kernel as ConsoleKernel;
use MacropaySolutions\Framework\Exceptions\Handler as ExceptionHandler;

class KernelTest extends \MacropaySolutions\KernelDev\Framework\Testing\TestCase
{
    /**
     * Creates the application.
     *
     * Needs to be implemented by subclasses.
     */
    public function createApplication()
    {
        $app = new Application();

        $app->configure('app');

        $app->singleton(ExceptionHandlerContract::class, [self::class, 'createExceptionHandler']);
        $app->singleton(ConsoleKernelContract::class, [self::class, 'createConsoleKernel']);

        return $app;
    }

    public function testItCanRerouteToSymfonyEvent()
    {
        $this->expectsEvents([CommandStarting::class, CommandFinished::class]);

        $this->consoleApp('cache:forget', ['key' => 'framework']);
    }
    protected function tearDown(): void
    {
        restore_error_handler();
        restore_exception_handler();
        parent::tearDown();
    }

    public static function createExceptionHandler()
    {
        return new ExceptionHandler();
    }

    public static function createConsoleKernel($app)
    {
        return tap(new ConsoleKernel($app), function ($kernel) {
            $kernel->rerouteSymfonyCommandEvents();
        });
    }
}
