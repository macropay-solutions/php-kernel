<?php

namespace MacropaySolutions\Kernel\Tests\Validation;

use MacropaySolutions\Kernel\Translation\ArrayLoader;
use MacropaySolutions\Kernel\Translation\Translator;
use MacropaySolutions\Kernel\Validation\Validator;
use PHPUnit\Framework\TestCase;

/**
 * Missing data was marked with the string "__missing__": a submitted "__missing__" value was skipped by
 * nested and wildcard rules.
 */
class MissingValueMarkerTest extends TestCase
{
    public function testSubmittedMarkerStringIsValidated(): void
    {
        $this->assertFalse(
            $this->passes(['profile' => ['age' => '__missing__']], ['profile.age' => 'sometimes|integer'])
        );
        $this->assertFalse(
            $this->passes(['items' => [['qty' => '__missing__']]], ['items.*.qty' => 'sometimes|integer'])
        );
        $this->assertFalse($this->passes(['items' => ['__missing__']], ['items.*' => 'sometimes|integer']));
    }

    public function testMissingValuesAreStillSkipped(): void
    {
        $this->assertTrue($this->passes(['items' => [[], ['qty' => 5]]], ['items.*.qty' => 'sometimes|integer']));
    }

    private function passes(array $data, array $rules): bool
    {
        return (new Validator(new Translator(new ArrayLoader(), 'en'), $data, $rules))->passes();
    }
}
