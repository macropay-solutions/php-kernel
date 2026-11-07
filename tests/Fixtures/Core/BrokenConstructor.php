<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Core;

class BrokenConstructor
{
    public function __construct()
    {
        $class = 'App\\MissingInsideConstructor';

        new $class();
    }
}
