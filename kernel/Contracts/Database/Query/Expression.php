<?php

namespace MacropaySolutions\Kernel\Contracts\Database\Query;

use MacropaySolutions\Kernel\Database\Grammar;

interface Expression
{
    /**
     * Get the value of the expression.
     *
     * @param \MacropaySolutions\Kernel\Database\Grammar $grammar
     * @return string|int|float
     */
    public function getValue(Grammar $grammar);
}
