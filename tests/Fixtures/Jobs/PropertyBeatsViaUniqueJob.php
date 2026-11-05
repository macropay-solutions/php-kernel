<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Jobs;

use MacropaySolutions\Kernel\Bus\InstanceDispatchable;
use MacropaySolutions\Kernel\Bus\Queueable;
use MacropaySolutions\Kernel\Contracts\Cache\Repository;
use MacropaySolutions\Kernel\Contracts\Queue\ShouldBeUnique;
use MacropaySolutions\Kernel\Contracts\Queue\ShouldQueue;
use MacropaySolutions\Kernel\Contracts\Queue\StorableCallable;

class PropertyBeatsViaUniqueJob implements ShouldQueue, StorableCallable, ShouldBeUnique
{
    use InstanceDispatchable;
    use Queueable;

    public string $uniqueCacheStore = 'array';

    public function uniqueVia(): Repository
    {
        return \app('cache')->store('locks');
    }

    public function handle(): void
    {
    }
}
