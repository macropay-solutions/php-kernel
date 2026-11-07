<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Core;

use MacropaySolutions\Kernel\Support\ServiceProvider;

class BootCountingProvider extends ServiceProvider
{
    public static int $registered = 0;

    public static int $booted = 0;

    public function register()
    {
        static::$registered++;
    }

    public function boot(): void
    {
        static::$booted++;
    }
}
