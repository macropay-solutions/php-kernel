<?php

namespace MacropaySolutions\Kernel\Queue\Connectors;

use MacropaySolutions\Kernel\Queue\SyncQueue;

class SyncConnector implements ConnectorInterface
{
    /**
     * Establish a queue connection.
     *
     * @param array $config
     * @return \MacropaySolutions\Kernel\Contracts\Queue\Queue
     */
    public function connect(array $config)
    {
        return new SyncQueue();
    }
}
