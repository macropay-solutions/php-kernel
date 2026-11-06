<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Events;

class StopPropagation
{
    public function handle(OrderShipped $event): bool
    {
        EventLog::$entries[] = 'stop:' . $event->orderId;

        return false;
    }
}
