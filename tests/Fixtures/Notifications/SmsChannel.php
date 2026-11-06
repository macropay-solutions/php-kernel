<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Notifications;

use MacropaySolutions\Kernel\Notifications\Notification;

class SmsChannel
{
    /**
     * @var string[]
     */
    public static array $sent = [];

    public function send(mixed $notifiable, Notification $notification): void
    {
        $phone = $notifiable->routeNotificationFor('sms', $notification);

        static::$sent[] = $phone . ':' . $notification->toSms($notifiable);
    }
}
