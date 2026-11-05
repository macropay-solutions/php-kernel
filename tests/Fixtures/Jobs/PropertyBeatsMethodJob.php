<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Jobs;

use MacropaySolutions\Kernel\Bus\InstanceDispatchable;
use MacropaySolutions\Kernel\Bus\Queueable;
use MacropaySolutions\Kernel\Contracts\Queue\ShouldQueue;
use MacropaySolutions\Kernel\Contracts\Queue\StorableCallable;

class PropertyBeatsMethodJob implements ShouldQueue, StorableCallable
{
    use InstanceDispatchable;
    use Queueable;

    public $tries = 7;

    public function tries(): int
    {
        return 4;
    }

    public function handle(): void
    {
    }
}
