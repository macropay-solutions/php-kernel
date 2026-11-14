<?php

namespace MacropaySolutions\Kernel\Tests\Validation;

use MacropaySolutions\Kernel\Translation\ArrayLoader;
use MacropaySolutions\Kernel\Translation\Translator;
use MacropaySolutions\Kernel\Validation\Validator;
use PHPUnit\Framework\TestCase;

/**
 * date_format parsed the value in the default timezone: a wall time skipped by a DST change was shifted
 * by an hour and the valid input was rejected.
 */
class DateFormatDstTest extends TestCase
{
    private string $timezone;

    protected function setUp(): void
    {
        $this->timezone = \date_default_timezone_get();
        \date_default_timezone_set('Europe/Bucharest');
    }

    protected function tearDown(): void
    {
        \date_default_timezone_set($this->timezone);
    }

    public function testWallTimeSkippedByDstPasses(): void
    {
        $this->assertTrue($this->passes('2025-03-30 03:30', 'date_format:Y-m-d H:i'));
    }

    public function testInvalidDatesStillFail(): void
    {
        $this->assertFalse($this->passes('2025-02-30 10:00', 'date_format:Y-m-d H:i'));
        $this->assertFalse($this->passes('2025-03-30 3:30', 'date_format:Y-m-d H:i'));
    }

    public function testFormatWithOffsetPasses(): void
    {
        $this->assertTrue($this->passes('2025-03-30T03:30:00+03:00', 'date_format:Y-m-d\TH:i:sP'));
    }

    private function passes(string $value, string $rule): bool
    {
        return (new Validator(new Translator(new ArrayLoader(), 'en'), ['when' => $value], ['when' => $rule]))
            ->passes();
    }
}
