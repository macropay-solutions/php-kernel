<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Jobs;

use MacropaySolutions\Kernel\Bus\InstanceDispatchable;
use MacropaySolutions\Kernel\Bus\Queueable;
use MacropaySolutions\Kernel\Contracts\Queue\ShouldBeUnique;
use MacropaySolutions\Kernel\Contracts\Queue\ShouldQueue;
use MacropaySolutions\Kernel\Contracts\Queue\StorableCallable;

class FailingUniqueJob implements ShouldQueue, StorableCallable, ShouldBeUnique
{
    use InstanceDispatchable;
    use Queueable;

    public function handle(): void
    {
        throw new \RuntimeException('boom');
    }
}
