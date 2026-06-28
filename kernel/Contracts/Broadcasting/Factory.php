<?php

namespace MacropaySolutions\Kernel\Contracts\Broadcasting;

interface Factory
{
    /**
     * Get a broadcaster implementation by name.
     *
     * @param string|null $name
     * @return \MacropaySolutions\Kernel\Contracts\Broadcasting\Broadcaster
     */
    public function connection($name = null);
}
