<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Jobs;

use MacropaySolutions\Kernel\Bus\InstanceDispatchable;
use MacropaySolutions\Kernel\Bus\Queueable;
use MacropaySolutions\Kernel\Contracts\Queue\ShouldQueue;
use MacropaySolutions\Kernel\Contracts\Queue\StorableCallable;

class FifoMethodsJob implements ShouldQueue, StorableCallable
{
    use InstanceDispatchable;
    use Queueable;

    public function messageGroup(): string
    {
        return 'obj-group';
    }

    public function deduplicationId(): string
    {
        return 'obj-dedup';
    }

    public function handle(): void
    {
    }
}
