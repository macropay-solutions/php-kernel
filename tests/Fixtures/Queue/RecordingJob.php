<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Queue;

use MacropaySolutions\Kernel\Bus\InstanceDispatchable;
use MacropaySolutions\Kernel\Bus\Queueable;
use MacropaySolutions\Kernel\Contracts\Queue\ShouldQueue;
use MacropaySolutions\Kernel\Contracts\Queue\StorableCallable;

class RecordingJob implements ShouldQueue, StorableCallable
{
    use InstanceDispatchable;
    use Queueable;

    /**
     * @var int[]
     */
    public static array $handled = [];

    public function handle(int $id): void
    {
        static::$handled[] = $id;
    }
}
