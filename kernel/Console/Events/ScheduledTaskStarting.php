<?php

namespace MacropaySolutions\Kernel\Console\Events;

use MacropaySolutions\Kernel\Console\Scheduling\Event;

class ScheduledTaskStarting
{
    /**
     * The scheduled event being run.
     *
     * @var \MacropaySolutions\Kernel\Console\Scheduling\Event
     */
    public $task;

    /**
     * Create a new event instance.
     *
     * @param \MacropaySolutions\Kernel\Console\Scheduling\Event $task
     * @return void
     */
    public function __construct(Event $task)
    {
        $this->task = $task;
    }
}
