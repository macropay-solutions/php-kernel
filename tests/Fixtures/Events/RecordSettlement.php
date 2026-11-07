<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Events;

class RecordSettlement
{
    public function handle(PaymentSettled $event): void
    {
        EventLog::$entries[] = 'settled:' . $event->paymentId;
    }
}
