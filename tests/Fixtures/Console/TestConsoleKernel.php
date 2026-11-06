<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Console;

use MacropaySolutions\Framework\Console\Kernel;

class TestConsoleKernel extends Kernel
{
    protected $commands = [
        GreetCommand::class,
        CallsOtherCommand::class,
    ];
}
