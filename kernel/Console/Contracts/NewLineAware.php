<?php

namespace MacropaySolutions\Kernel\Console\Contracts;

interface NewLineAware
{
    /**
     * How many trailing newlines were written.
     *
     * @return int
     */
    public function newLinesWritten();
}
