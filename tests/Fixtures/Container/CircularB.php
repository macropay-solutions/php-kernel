<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Container;

class CircularB
{
    public function __construct(public CircularA $a)
    {
    }
}
