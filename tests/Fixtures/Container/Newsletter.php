<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Container;

class Newsletter
{
    public function __construct(public Mailer $mailer)
    {
    }

    public function send(Mailer $mailer, string $subject = 'News'): string
    {
        return $subject . ' at ' . $mailer->clock->now();
    }
}
