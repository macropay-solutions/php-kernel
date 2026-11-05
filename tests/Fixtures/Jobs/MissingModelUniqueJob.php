<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Jobs;

use MacropaySolutions\Kernel\Bus\InstanceDispatchable;
use MacropaySolutions\Kernel\Bus\Queueable;
use MacropaySolutions\Kernel\Contracts\Queue\ShouldBeUnique;
use MacropaySolutions\Kernel\Contracts\Queue\ShouldQueue;
use MacropaySolutions\Kernel\Contracts\Queue\StorableCallable;
use MacropaySolutions\Kernel\Database\Obvious\ModelNotFoundException;

class MissingModelUniqueJob implements ShouldQueue, StorableCallable, ShouldBeUnique
{
    use InstanceDispatchable;
    use Queueable;

    public $deleteWhenMissingModels = true;

    public function handle(): void
    {
        // What restoring a deleted model argument throws on the worker
        throw new ModelNotFoundException();
    }
}
