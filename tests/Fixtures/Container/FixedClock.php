<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Container;

class FixedClock implements Clock
{
    public function __construct(public string $time = '10:00')
    {
    }

    public function now(): string
    {
        return $this->time;
    }
}
