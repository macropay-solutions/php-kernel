<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Queue;

use MacropaySolutions\Kernel\Bus\InstanceDispatchable;
use MacropaySolutions\Kernel\Bus\Queueable;
use MacropaySolutions\Kernel\Contracts\Queue\ShouldQueue;
use MacropaySolutions\Kernel\Contracts\Queue\StorableCallable;

class RetryingJob implements ShouldQueue, StorableCallable
{
    use InstanceDispatchable;
    use Queueable;

    public static int $attempts = 0;

    /**
     * @var string[]
     */
    public static array $failures = [];

    public $tries = 2;

    public $backoff = 10;

    public function handle(string $reason): void
    {
        static::$attempts++;

        throw new \RuntimeException($reason);
    }

    public function failed(\Throwable $e, string $reason = ''): void
    {
        static::$failures[] = $e->getMessage() . '|' . $reason;
    }
}
