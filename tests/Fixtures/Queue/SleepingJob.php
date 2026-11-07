<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Queue;

use MacropaySolutions\Kernel\Bus\InstanceDispatchable;
use MacropaySolutions\Kernel\Bus\Queueable;
use MacropaySolutions\Kernel\Contracts\Queue\ShouldQueue;
use MacropaySolutions\Kernel\Contracts\Queue\StorableCallable;

class SleepingJob implements ShouldQueue, StorableCallable
{
    use InstanceDispatchable;
    use Queueable;

    public $timeout = 1;

    public $tries = 3;

    public $failOnTimeout = true;

    public function handle(int $seconds): void
    {
        \sleep($seconds);
    }
}
