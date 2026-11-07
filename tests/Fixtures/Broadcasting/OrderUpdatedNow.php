<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Broadcasting;

use MacropaySolutions\Kernel\Contracts\Broadcasting\ShouldBroadcastNow;

class OrderUpdatedNow extends OrderUpdated implements ShouldBroadcastNow
{
}
