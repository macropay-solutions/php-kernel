<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Container;

class Factories
{
    public static int $made = 0;

    public static function clock(): Clock
    {
        static::$made++;

        return new FixedClock('12:00');
    }

    public static function logging(Mailer $mailer): LoggingMailer
    {
        return new LoggingMailer($mailer->clock, 'logging@example.com');
    }
}
