<?php
namespace MacropaySolutions\Kernel\Bus;

use MacropaySolutions\Framework\Bus\PendingDispatch;
use MacropaySolutions\Kernel\Contracts\Bus\Dispatcher;

trait InstanceDispatchable
{
    /**
     * Dispatch the configured job instance.
     */
    public function dispatch(): PendingDispatch
    {
        return new PendingDispatch($this);
    }

    /**
     * Dispatch the job immediately in the current process.
     */
    public function dispatchNow(mixed $handler = null): mixed
    {
        return \app(Dispatcher::class)->dispatchNow($this, $handler);
    }

    /**
     * Dispatch the job synchronously.
     */
    public function dispatchSync(mixed $handler = null): mixed
    {
        return \app(Dispatcher::class)->dispatchSync($this, $handler);
    }
}
