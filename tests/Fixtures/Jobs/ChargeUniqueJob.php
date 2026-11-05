<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Jobs;

use MacropaySolutions\Kernel\Bus\InstanceDispatchable;
use MacropaySolutions\Kernel\Bus\Queueable;
use MacropaySolutions\Kernel\Contracts\Queue\ShouldBeUnique;
use MacropaySolutions\Kernel\Contracts\Queue\ShouldQueue;
use MacropaySolutions\Kernel\Contracts\Queue\StorableCallable;

class ChargeUniqueJob implements ShouldQueue, StorableCallable, ShouldBeUnique
{
    use InstanceDispatchable;
    use Queueable;

    public function uniqueId(): string
    {
        return '5';
    }

    public function handle(): void
    {
    }
}
