<?php

namespace MacropaySolutions\Kernel\Database\Console\Seeds;

use MacropaySolutions\Kernel\Database\Obvious\Model;

trait WithoutModelEvents
{
    /**
     * Prevent model events from being dispatched by the given callback.
     *
     * @param callable $callback
     * @return callable
     */
    public function withoutModelEvents(callable $callback)
    {
        return fn() => Model::withoutEvents($callback);
    }
}
