<?php

namespace MacropaySolutions\Kernel\Contracts\Queue;

use MacropaySolutions\Kernel\Queue\CallQueuedCallable;

interface StorableCallable
{
    /**
     * Convert the queueable entity into a storable array-callable wrapper.
     */
    public function toStorableCallable(): CallQueuedCallable;
}