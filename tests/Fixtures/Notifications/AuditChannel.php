<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Notifications;

use MacropaySolutions\Kernel\Notifications\Notification;

/**
 * Registered on the channel manager under the "audit" driver name.
 */
class AuditChannel
{
    /**
     * @var string[]
     */
    public static array $logged = [];

    public static function make(): static
    {
        return new static();
    }

    public function send(mixed $notifiable, Notification $notification): void
    {
        static::$logged[] = $notification::class . '#' . $notification->invoiceId;
    }
}
