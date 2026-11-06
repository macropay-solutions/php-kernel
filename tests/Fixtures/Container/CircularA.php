<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Container;

class CircularA
{
    public function __construct(public CircularB $b)
    {
    }
}
