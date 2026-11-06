<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Events;

class RecordShipment
{
    public function handle(OrderShipped $event): void
    {
        EventLog::$entries[] = 'record:' . $event->orderId;
    }
}
