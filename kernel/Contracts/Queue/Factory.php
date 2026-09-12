<?php

namespace MacropaySolutions\Kernel\Contracts\Queue;

interface Factory
{
    /**
     * Resolve a queue connection instance.
     *
     * @param string|null $name
     * @return \MacropaySolutions\Kernel\Contracts\Queue\Queue
     */
    public function connection($name = null);

    /**
     * Dynamically pass calls to the default connection.
     */
    public function fwd(): \MacropaySolutions\Kernel\Contracts\Queue\Queue;
}
