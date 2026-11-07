<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Events;

use MacropaySolutions\Kernel\Contracts\Events\ShouldDispatchAfterCommit;

class PaymentSettled implements ShouldDispatchAfterCommit
{
    public function __construct(public int $paymentId)
    {
    }
}
