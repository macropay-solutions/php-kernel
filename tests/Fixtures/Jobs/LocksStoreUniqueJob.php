<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Jobs;

use MacropaySolutions\Kernel\Bus\InstanceDispatchable;
use MacropaySolutions\Kernel\Bus\Queueable;
use MacropaySolutions\Kernel\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use MacropaySolutions\Kernel\Contracts\Queue\ShouldQueue;
use MacropaySolutions\Kernel\Contracts\Queue\StorableCallable;

class LocksStoreUniqueJob implements ShouldQueue, StorableCallable, ShouldBeUniqueUntilProcessing
{
    use InstanceDispatchable;
    use Queueable;

    public string $uniqueCacheStore = 'locks';

    public function handle(int $id): void
    {
    }
}
