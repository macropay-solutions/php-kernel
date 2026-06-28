<?php

namespace MacropaySolutions\Kernel\Queue\Failed;

use DateTimeInterface;

interface PrunableFailedJobProvider
{
    /**
     * Prune all the entries older than the given date.
     *
     * @param \DateTimeInterface $before
     * @return int
     */
    public function prune(DateTimeInterface $before);
}
