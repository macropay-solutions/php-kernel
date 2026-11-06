<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Notifications;

use MacropaySolutions\Kernel\Notifications\Notification;

class InvoicePaid extends Notification
{
    public function __construct(public int $invoiceId = 0)
    {
    }

    public function via(mixed $notifiable): array
    {
        return [SmsChannel::class, 'audit'];
    }

    public function toSms(mixed $notifiable): string
    {
        return 'Invoice ' . $this->invoiceId . ' paid';
    }
}
