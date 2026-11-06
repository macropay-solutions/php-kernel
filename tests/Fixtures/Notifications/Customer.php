<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Notifications;

use MacropaySolutions\Kernel\Notifications\Notifiable;

class Customer
{
    use Notifiable;

    public function __construct(public string $email = 'ana@example.com', public string $phone = '+40700000000')
    {
    }

    public function routeNotificationForSms(): string
    {
        return $this->phone;
    }

    public function getKey(): string
    {
        return $this->email;
    }
}
