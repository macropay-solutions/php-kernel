<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Events;

class InventoryManager
{
    public static function reserve(OrderShipped $event): void
    {
        EventLog::$entries[] = 'reserve:' . $event->orderId;
    }

    public static function reserveById(int $orderId): void
    {
        EventLog::$entries[] = 'reserve-by-id:' . $orderId;
    }

    public static function answer(string $question): string
    {
        return 'answer to ' . $question;
    }
}
