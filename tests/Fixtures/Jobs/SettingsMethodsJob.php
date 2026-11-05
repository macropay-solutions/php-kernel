<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Jobs;

use MacropaySolutions\Kernel\Bus\InstanceDispatchable;
use MacropaySolutions\Kernel\Bus\Queueable;
use MacropaySolutions\Kernel\Contracts\Queue\ShouldQueue;
use MacropaySolutions\Kernel\Contracts\Queue\StorableCallable;

class SettingsMethodsJob implements ShouldQueue, StorableCallable
{
    use InstanceDispatchable;
    use Queueable;

    public function tries(): int
    {
        return 4;
    }

    public function backoff(): array
    {
        return [1, 5];
    }

    public function handle(): void
    {
    }
}
