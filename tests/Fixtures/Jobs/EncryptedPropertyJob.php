<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Jobs;

use MacropaySolutions\Kernel\Bus\InstanceDispatchable;
use MacropaySolutions\Kernel\Bus\Queueable;
use MacropaySolutions\Kernel\Contracts\Queue\ShouldQueue;
use MacropaySolutions\Kernel\Contracts\Queue\StorableCallable;

class EncryptedPropertyJob implements ShouldQueue, StorableCallable
{
    use InstanceDispatchable;
    use Queueable;

    public bool $shouldBeEncrypted = true;

    public function handle(int $id): void
    {
    }
}
