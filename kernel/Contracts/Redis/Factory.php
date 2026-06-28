<?php

namespace MacropaySolutions\Kernel\Contracts\Redis;

interface Factory
{
    /**
     * Get a Redis connection by name.
     *
     * @param string|null $name
     * @return \MacropaySolutions\Kernel\Redis\Connections\Connection
     */
    public function connection($name = null);
}
