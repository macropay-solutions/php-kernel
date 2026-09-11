<?php

namespace MacropaySolutions\Kernel\Support\Traits;

trait Tappable
{
    /**
     * Call the given Closure with this instance then return the instance.
     */
    public function tap(callable $callback): static
    {
        return \tap($this, $callback);
    }
}
