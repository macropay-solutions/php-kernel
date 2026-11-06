<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Notifications;

use MacropaySolutions\Kernel\Bus\Queueable;
use MacropaySolutions\Kernel\Contracts\Queue\ShouldQueue;

class QueuedInvoicePaid extends InvoicePaid implements ShouldQueue
{
    use Queueable;

    public function via(mixed $notifiable): array
    {
        return [SmsChannel::class];
    }
}
