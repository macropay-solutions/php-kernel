<?php

namespace MacropaySolutions\Kernel\Tests\Validation;

use MacropaySolutions\Kernel\Translation\ArrayLoader;
use MacropaySolutions\Kernel\Translation\Translator;
use MacropaySolutions\Kernel\Validation\Rule;
use MacropaySolutions\Kernel\Validation\Validator;
use PHPUnit\Framework\TestCase;

class ValidatorEdgeInputsTest extends TestCase
{
    public function testInArrayComparesAsStrings(): void
    {
        $this->assertFalse($this->passes(['x' => '1e1', 'list' => ['10']], ['x' => 'in_array:list.*']));
        $this->assertFalse($this->passes(['x' => '0', 'list' => ['0.0']], ['x' => 'in_array:list.*']));
        $this->assertTrue($this->passes(['x' => '10', 'list' => [10]], ['x' => 'in_array:list.*']));
    }

    public function testNotRegexFailsWhenTheMatchFails(): void
    {
        $jit = \ini_get('pcre.jit');
        $limit = \ini_get('pcre.backtrack_limit');
        \ini_set('pcre.jit', '0');
        \ini_set('pcre.backtrack_limit', '1000');

        try {
            $this->assertFalse($this->passes(['x' => \str_repeat('a', 30) . 'b'], ['x' => ['not_regex:/(a+)+$/']]));
        } finally {
            \ini_set('pcre.jit', $jit);
            \ini_set('pcre.backtrack_limit', $limit);
        }
    }

    public function testSizeMessageForAnotherTypeFallsBackToTheDefault(): void
    {
        $validator = $this->validator(['x' => 5], ['x' => 'integer|size:3'], ['x.size' => ['string' => 'S']]);

        $this->assertFalse($validator->passes());
        $this->assertSame(['x' => ['validation.size.numeric']], $validator->errors()->toArray());
    }

    public function testAnEmptyKeyGetsTheWildcardRules(): void
    {
        $validator = $this->validator(
            ['users' => ['' => ['role' => 'admin']]],
            ['users.*.invite_code' => ['required_if:users.*.role,admin']]
        );

        $this->assertFalse($validator->passes());
        $this->assertTrue($validator->errors()->has('users..invite_code'));
    }

    public function testForEachOnALargeArrayIsNotQuadratic(): void
    {
        $start = \microtime(true);

        $this->assertTrue($this->passes(
            ['x' => \array_fill(0, 5000, ['a' => 1])],
            ['x.*' => Rule::forEach(fn() => ['a' => 'integer'])]
        ));
        $this->assertLessThan(5, \microtime(true) - $start);
    }

    private function passes(array $data, array $rules): bool
    {
        return $this->validator($data, $rules)->passes();
    }

    private function validator(array $data, array $rules, array $messages = []): Validator
    {
        return new Validator(new Translator(new ArrayLoader(), 'en'), $data, $rules, $messages);
    }
}
