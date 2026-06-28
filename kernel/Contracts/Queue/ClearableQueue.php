<?php

namespace MacropaySolutions\Kernel\Contracts\Queue;

interface ClearableQueue
{
    /**
     * Delete all the jobs from the queue.
     *
     * @param string $queue
     * @return int
     */
    public function clear($queue);
}
