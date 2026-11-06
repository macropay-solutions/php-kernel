<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Scheduling;

class ScheduledTasks
{
    /**
     * @var string[]
     */
    public static array $ran = [];

    public static function nightlyReport(): void
    {
        static::$ran[] = 'nightly';
    }

    public static function everyFiveMinutes(): void
    {
        static::$ran[] = 'five';
    }

    public static function weekdayCleanup(): void
    {
        static::$ran[] = 'weekday';
    }

    public static function failing(): void
    {
        static::$ran[] = 'failing';

        throw new \RuntimeException('task failed');
    }

    public static function afterFailing(): void
    {
        static::$ran[] = 'after-failing';
    }
}
