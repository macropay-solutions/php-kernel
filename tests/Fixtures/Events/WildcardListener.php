<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Events;

class WildcardListener
{
    public function handle(string $eventName, array $payload): void
    {
        EventLog::$entries[] = 'wildcard:' . $eventName . ':' . \json_encode($payload);
    }
}
