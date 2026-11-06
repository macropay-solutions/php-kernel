<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Console;

use MacropaySolutions\Kernel\Console\Command;

class CallsOtherCommand extends Command
{
    protected $signature = 'test:chain';

    public function handle(): int
    {
        return $this->call('test:greet', ['name' => 'chained', '--shout' => true]);
    }
}
