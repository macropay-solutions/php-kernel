<?php

namespace MacropaySolutions\Kernel\Contracts\Support;

interface CanBeEscapedWhenCastToString
{
    /**
     * Indicate that the object's string representation should be escaped when __toString is invoked.
     */
    public function escapeWhenCastingToString(bool $escape = true): static;
}
