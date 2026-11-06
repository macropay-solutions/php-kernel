<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Queue;

use MacropaySolutions\Kernel\Bus\InstanceDispatchable;
use MacropaySolutions\Kernel\Bus\Queueable;
use MacropaySolutions\Kernel\Contracts\Queue\ShouldBeUnique;
use MacropaySolutions\Kernel\Contracts\Queue\ShouldQueue;
use MacropaySolutions\Kernel\Contracts\Queue\StorableCallable;

class UniqueRecordingJob implements ShouldQueue, StorableCallable, ShouldBeUnique
{
    use InstanceDispatchable;
    use Queueable;

    public static int $runs = 0;

    public function uniqueId(): string
    {
        return 'report';
    }

    public function handle(): void
    {
        static::$runs++;
    }
}
