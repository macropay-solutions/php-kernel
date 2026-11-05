<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Jobs;

use MacropaySolutions\Kernel\Bus\InstanceDispatchable;
use MacropaySolutions\Kernel\Bus\Queueable;
use MacropaySolutions\Kernel\Contracts\Queue\ShouldQueue;
use MacropaySolutions\Kernel\Contracts\Queue\StorableCallable;

class HiddenSettingsJob implements ShouldQueue, StorableCallable
{
    use InstanceDispatchable;
    use Queueable;

    protected $tries = 5;

    protected bool $shouldBeEncrypted = true;

    public function handle(): void
    {
    }

    protected function backoff(): int
    {
        return 3;
    }

    private function failed(\Throwable $e): void
    {
    }
}
