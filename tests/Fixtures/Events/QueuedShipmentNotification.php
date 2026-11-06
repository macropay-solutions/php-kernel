<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Events;

use MacropaySolutions\Kernel\Contracts\Queue\ShouldQueue;

class QueuedShipmentNotification implements ShouldQueue
{
    public function handle(OrderShipped $event): void
    {
        EventLog::$entries[] = 'queued:' . $event->orderId . ':' . $event->carrier;
    }
}
