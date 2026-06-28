<?php

namespace MacropaySolutions\Kernel\Support\Traits;

trait Tappable
{
    /**
     * Call the given Closure with this instance then return the instance.
     *
     * @param callable|null $callback
     * @return $this|\MacropaySolutions\Kernel\Support\HigherOrderTapProxy
     */
    public function tap($callback = null)
    {
        return tap($this, $callback);
    }
}
