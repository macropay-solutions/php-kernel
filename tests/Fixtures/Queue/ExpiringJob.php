<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Queue;

use Carbon\Carbon;
use MacropaySolutions\Kernel\Bus\InstanceDispatchable;
use MacropaySolutions\Kernel\Bus\Queueable;
use MacropaySolutions\Kernel\Contracts\Queue\ShouldQueue;
use MacropaySolutions\Kernel\Contracts\Queue\StorableCallable;

class ExpiringJob implements ShouldQueue, StorableCallable
{
    use InstanceDispatchable;
    use Queueable;

    public static int $attempts = 0;

    public function retryUntil(): \DateTimeInterface
    {
        return Carbon::now()->addSeconds(60);
    }

    public function messageGroup(): string
    {
        return 'expiring-group';
    }

    public function handle(): void
    {
        static::$attempts++;

        throw new \RuntimeException('still failing');
    }
}
