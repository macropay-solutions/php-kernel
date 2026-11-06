<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Events;

class OrderShipped
{
    public function __construct(public int $orderId, public string $carrier = 'dhl')
    {
    }
}
