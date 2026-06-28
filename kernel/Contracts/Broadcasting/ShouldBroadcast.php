<?php

namespace MacropaySolutions\Kernel\Contracts\Broadcasting;

interface ShouldBroadcast
{
    /**
     * Get the channels the event should broadcast on.
     *
     * @return \MacropaySolutions\Kernel\Broadcasting\Channel|\MacropaySolutions\Kernel\Broadcasting\Channel[]|string[]|string
     */
    public function broadcastOn();
}
