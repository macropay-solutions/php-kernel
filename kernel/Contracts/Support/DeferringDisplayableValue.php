<?php

namespace MacropaySolutions\Kernel\Contracts\Support;

interface DeferringDisplayableValue
{
    /**
     * Resolve the displayable value that the class is deferring.
     *
     * @return \MacropaySolutions\Kernel\Contracts\Support\Htmlable|string
     */
    public function resolveDisplayableValue();
}
