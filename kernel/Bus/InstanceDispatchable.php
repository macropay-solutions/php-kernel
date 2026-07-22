<?php

namespace MacropaySolutions\Kernel\Bus;

use MacropaySolutions\Framework\Bus\PendingDispatch;
use MacropaySolutions\Kernel\Contracts\Bus\Dispatcher;
use MacropaySolutions\Kernel\Contracts\Queue\ShouldBeUnique;
use MacropaySolutions\Kernel\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use MacropaySolutions\Kernel\Queue\CallQueuedCallable;
use MacropaySolutions\Kernel\Queue\UniqueCallQueuedCallable;
use MacropaySolutions\Kernel\Queue\UniqueUntilProcessingCallQueuedCallable;

trait InstanceDispatchable
{
    private array $instanceDispatchableNamedPrimitives = [];

    protected bool $createdViaInstanceDispatchable = false;

    public static function new(array $namedPrimitives = []): static
    {
        $instance = new static(); // no params on construct because this will be instantiated twice.

        if ([] !== $namedPrimitives && \array_is_list($namedPrimitives)) {
            throw new \RuntimeException('$namedPrimitives is list');
        }

        $instance->instanceDispatchableNamedPrimitives = $namedPrimitives;
        $instance->createdViaInstanceDispatchable = true;

        return $instance;
    }

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

    public function toStorableCallable(): CallQueuedCallable
    {
        if (!$this->createdViaInstanceDispatchable) {
            throw new \RuntimeException('Strict Queue Mode: You must instantiate "' .
                static::class . '" using the static factory: static::new(...)->dispatch();');
        }

        $callable = CallQueuedCallable::createFrom(
            $this,
            [static::class, 'handle', $this->instanceDispatchableNamedPrimitives]
        );

        if (\property_exists($this, 'chainConnection')) {
            $callable->connection = $this->connection ?? null;
            $callable->queue = $this->queue ?? null;
            $callable->messageGroup = $this->messageGroup ?? null;
            $callable->chainConnection = $this->chainConnection ?? null;
            $callable->chainQueue = $this->chainQueue ?? null;
            $callable->chainCatchCallbacks = $this->chainCatchCallbacks ?? null;
            $callable->delay = $this->delay ?? null;
            $callable->afterCommit = $this->afterCommit ?? null;
            $callable->chained = $this->chained ?? [];
        }

        return $callable;
    }
}
