<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\EventCache;

use MacropaySolutions\Framework\Providers\EventServiceProvider;

class ConfigurableEventProvider extends EventServiceProvider
{
    public static array $map = [];

    public function listens()
    {
        return static::$map;
    }
}
