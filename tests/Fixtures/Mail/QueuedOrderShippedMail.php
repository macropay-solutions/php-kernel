<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Mail;

use MacropaySolutions\Kernel\Contracts\Queue\ShouldQueue;

class QueuedOrderShippedMail extends OrderShippedMail implements ShouldQueue
{
}
