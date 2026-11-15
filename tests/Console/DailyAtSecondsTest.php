<?php

namespace MacropaySolutions\Kernel\Tests\Console;

use MacropaySolutions\Kernel\Console\Scheduling\CallbackEvent;
use MacropaySolutions\Kernel\Console\Scheduling\EventMutex;
use PHPUnit\Framework\TestCase;

/**
 * dailyAt('10:30:00') dropped the minutes and ran at 10:00.
 */
class DailyAtSecondsTest extends TestCase
{
    public function testSecondsAreIgnoredNotMinutes(): void
    {
        $event = new CallbackEvent($this->createStub(EventMutex::class), static fn() => null);

        $this->assertSame('30 10 * * *', $event->dailyAt('10:30:00')->getExpression());
        $this->assertSame('30 10 * * *', $event->dailyAt('10:30')->getExpression());
    }
}
