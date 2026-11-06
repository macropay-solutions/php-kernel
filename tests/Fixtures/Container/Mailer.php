<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Container;

class Mailer
{
    public function __construct(public Clock $clock, public string $from = 'noreply@example.com')
    {
    }
}
