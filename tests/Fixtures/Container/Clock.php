<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Container;

interface Clock
{
    public function now(): string;
}
