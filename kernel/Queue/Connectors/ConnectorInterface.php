<?php

namespace MacropaySolutions\Kernel\Queue\Connectors;

interface ConnectorInterface
{
    /**
     * Establish a queue connection.
     *
     * @param array $config
     * @return \MacropaySolutions\Kernel\Contracts\Queue\Queue
     */
    public function connect(array $config);
}
