<?php

namespace MacropaySolutions\Kernel\Console\Events;

use MacropaySolutions\Kernel\Console\Scheduling\Event;

class ScheduledTaskFinished
{
    /**
     * The scheduled event that ran.
     *
     * @var \MacropaySolutions\Kernel\Console\Scheduling\Event
     */
    public $task;

    /**
     * The runtime of the scheduled event.
     *
     * @var float
     */
    public $runtime;

    /**
     * Create a new event instance.
     *
     * @param \MacropaySolutions\Kernel\Console\Scheduling\Event $task
     * @param float $runtime
     * @return void
     */
    public function __construct(Event $task, $runtime)
    {
        $this->task = $task;
        $this->runtime = $runtime;
    }
}
