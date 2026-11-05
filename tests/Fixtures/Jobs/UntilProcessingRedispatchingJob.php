<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Jobs;

use MacropaySolutions\Kernel\Bus\InstanceDispatchable;
use MacropaySolutions\Kernel\Bus\Queueable;
use MacropaySolutions\Kernel\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use MacropaySolutions\Kernel\Contracts\Queue\ShouldQueue;
use MacropaySolutions\Kernel\Contracts\Queue\StorableCallable;

class UntilProcessingRedispatchingJob implements ShouldQueue, StorableCallable, ShouldBeUniqueUntilProcessing
{
    use InstanceDispatchable;
    use Queueable;

    /**
     * Dispatching the same job while it runs only works when its lock is already released.
     */
    public function handle(): void
    {
        static::new()->dispatch();
    }
}
