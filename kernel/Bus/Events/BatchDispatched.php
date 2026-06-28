<?php

namespace MacropaySolutions\Kernel\Bus\Events;

use MacropaySolutions\Kernel\Bus\Batch;

class BatchDispatched
{
    /**
     * The batch instance.
     *
     * @var \MacropaySolutions\Kernel\Bus\Batch
     */
    public $batch;

    /**
     * Create a new event instance.
     *
     * @param \MacropaySolutions\Kernel\Bus\Batch $batch
     * @return void
     */
    public function __construct(Batch $batch)
    {
        $this->batch = $batch;
    }
}
