<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Log;

use MacropaySolutions\Kernel\Log\Events\MessageLogged;

class RecordLogMessages
{
    /**
     * @var string[]
     */
    public static array $messages = [];

    public function handle(MessageLogged $event): void
    {
        static::$messages[] = $event->level . ':' . $event->message;
    }
}
