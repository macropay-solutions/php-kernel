<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Jobs;

use MacropaySolutions\Kernel\Bus\InstanceDispatchable;
use MacropaySolutions\Kernel\Bus\Queueable;
use MacropaySolutions\Kernel\Contracts\Queue\ShouldQueue;
use MacropaySolutions\Kernel\Contracts\Queue\StorableCallable;

class UnusableSettingsMethodsJob implements ShouldQueue, StorableCallable
{
    use InstanceDispatchable;
    use Queueable;

    public function tries(int $attempt): int
    {
        return $attempt;
    }

    public function backoff(): array
    {
        throw new \RuntimeException('backoff failed');
    }

    public function retryUntil(string $until): int
    {
        return 1;
    }

    public function handle(): void
    {
    }
}
