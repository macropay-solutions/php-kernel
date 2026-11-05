<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Jobs;

use MacropaySolutions\Kernel\Bus\InstanceDispatchable;
use MacropaySolutions\Kernel\Bus\Queueable;
use MacropaySolutions\Kernel\Contracts\Queue\ShouldQueue;
use MacropaySolutions\Kernel\Contracts\Queue\StorableCallable;

class SettingsPropertiesJob implements ShouldQueue, StorableCallable
{
    use InstanceDispatchable;
    use Queueable;

    /**
     * @var array<int, array{0: string, 1: int}>
     */
    public static array $failures = [];

    public $tries = 5;

    public $timeout = 120;

    public $maxExceptions = 3;

    public $backoff = '1,5,10';

    public $failOnTimeout = true;

    public function retryUntil(): \DateTimeInterface
    {
        return new \DateTimeImmutable('@1900000000');
    }

    public function handle(int $podcastId): void
    {
    }

    public function failed(\Throwable $e, int $podcastId = 0): void
    {
        static::$failures[] = [$e->getMessage(), $podcastId];
    }
}
