<?php

namespace MacropaySolutions\Kernel\Tests\Validation;

use MacropaySolutions\Kernel\Translation\ArrayLoader;
use MacropaySolutions\Kernel\Translation\Translator;
use MacropaySolutions\Kernel\Validation\Validator;
use PHPUnit\Framework\TestCase;

/**
 * Date comparison rules compared an invalid date as null: date_equals / before passed for values that are not dates.
 * A compared field with its own date_format is parsed with that format.
 */
class DateComparisonRulesTest extends TestCase
{
    public function testInvalidDatesFailTheComparisonRules(): void
    {
        $this->assertFalse($this->passes(['x' => 'invalid-date'], ['x' => 'date_equals:1970-01-01 00:00:00']));
        $this->assertFalse($this->passes(['x' => '1970-01-01 00:00:00'], ['x' => 'date_equals:invalid-date']));
        $this->assertFalse($this->passes(['x' => 'invalid-date'], ['x' => 'before:2030-01-01']));
        $this->assertFalse($this->passes(['x' => 'invalid-date'], ['x' => 'before_or_equal:2030-01-01']));
        $this->assertFalse($this->passes(['x' => '2024-01-01'], ['x' => 'after:invalid-date']));
    }

    public function testValidDatesStillCompare(): void
    {
        $this->assertTrue($this->passes(['x' => '1970-01-01 00:00:00'], ['x' => 'date_equals:1970-01-01']));
        $this->assertTrue($this->passes(['x' => '2024-01-01'], ['x' => 'before:2024-02-01']));
        $this->assertFalse($this->passes(['x' => '2024-03-01'], ['x' => 'before:2024-02-01']));
    }

    public function testComparedFieldUsesItsOwnDateFormat(): void
    {
        $this->assertTrue($this->passes(
            ['start' => '2024-01-01', 'end' => '13/01/2024'],
            ['start' => 'date_format:Y-m-d|before:end', 'end' => 'date_format:d/m/Y']
        ));
        $this->assertFalse($this->passes(
            ['start' => '2024-02-01', 'end' => '13/01/2024'],
            ['start' => 'date_format:Y-m-d|before:end', 'end' => 'date_format:d/m/Y']
        ));
    }

    private function passes(array $data, array $rules): bool
    {
        return (new Validator(new Translator(new ArrayLoader(), 'en'), $data, $rules))->passes();
    }
}
