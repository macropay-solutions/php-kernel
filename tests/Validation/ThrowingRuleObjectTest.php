<?php

namespace MacropaySolutions\Kernel\Tests\Validation;

use MacropaySolutions\Kernel\Contracts\Validation\Rule;
use MacropaySolutions\Kernel\Contracts\Validation\ValidationRule;
use MacropaySolutions\Kernel\Translation\ArrayLoader;
use MacropaySolutions\Kernel\Translation\Translator;
use MacropaySolutions\Kernel\Validation\Validator;
use PHPUnit\Framework\TestCase;

class ThrowingRuleObjectTest extends TestCase
{
    public function testARuleObjectThatThrowsFailsTheFieldWithItsMessage(): void
    {
        $validator = $this->validator(['code' => 'x'], ['code' => [new ThrowingRuleObjectTestRule(), 'string']]);

        $this->assertFalse($validator->passes());
        $this->assertSame(['code' => ['The code is invalid.']], $validator->errors()->toArray());
        $this->assertSame([ThrowingRuleObjectTestRule::class, 'Bail'], \array_keys($validator->failed()['code']));
    }

    public function testACustomMessageIsUsedAndAThrowingMessageFallsBackToTheClass(): void
    {
        $validator = $this->validator(
            ['a' => 'x', 'b' => 'x'],
            ['a' => [new ThrowingRuleObjectTestRule()], 'b' => [new ThrowingRuleObjectTestRule(true)]],
            ['a.' . ThrowingRuleObjectTestRule::class => 'Custom a.']
        );

        $this->assertFalse($validator->passes());
        $this->assertSame(
            ['a' => ['Custom a.'], 'b' => [ThrowingRuleObjectTestRule::class]],
            $validator->errors()->toArray()
        );
    }

    public function testAnInvokableRuleThatThrowsFailsTheField(): void
    {
        $validator = $this->validator(['code' => 'x'], ['code' => [new ThrowingRuleObjectTestInvokableRule()]]);

        $this->assertFalse($validator->passes());
        $this->assertSame(
            [ThrowingRuleObjectTestInvokableRule::class, 'Bail'],
            \array_keys($validator->failed()['code'])
        );
    }

    private function validator(array $data, array $rules, array $messages = []): Validator
    {
        return new Validator(new Translator(new ArrayLoader(), 'en'), $data, $rules, $messages);
    }
}

class ThrowingRuleObjectTestRule implements Rule
{
    public function __construct(private bool $messageThrows = false)
    {
    }

    public function passes($attribute, $value)
    {
        throw new \RuntimeException('service down');
    }

    public function message()
    {
        return $this->messageThrows ? throw new \RuntimeException('no message') : 'The :attribute is invalid.';
    }
}

class ThrowingRuleObjectTestInvokableRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, \Closure $fail): void
    {
        throw new \RuntimeException('service down');
    }
}
