<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Console;

use MacropaySolutions\Kernel\Console\Command;

class GreetCommand extends Command
{
    protected $signature = 'test:greet
        {name : Who to greet}
        {titles?* : Optional titles}
        {--times=1 : How many greetings}
        {--shout : Use upper case}';

    protected $description = 'Greets someone';

    public function handle(): int
    {
        if ($this->argument('name') === 'nobody') {
            $this->error('Nobody to greet.');

            return self::FAILURE;
        }

        $greeting = \implode(' ', ['Hello', ...$this->argument('titles'), $this->argument('name')]);

        if ($this->option('shout')) {
            $greeting = \strtoupper($greeting);
        }

        for ($i = 0; $i < (int)$this->option('times'); $i++) {
            $this->line($greeting);
        }

        return self::SUCCESS;
    }
}
