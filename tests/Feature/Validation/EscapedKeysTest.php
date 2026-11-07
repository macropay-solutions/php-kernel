<?php

namespace MacropaySolutions\Kernel\Tests\Feature\Validation;

use MacropaySolutions\Kernel\Contracts\Validation\Factory;
use MacropaySolutions\Kernel\Tests\ApplicationTestCase;

/**
 * Backported validator fixes: escaped dots in rule keys and parameters, and numeric first-level keys.
 */
class EscapedKeysTest extends ApplicationTestCase
{
    public function test_an_escaped_dot_targets_a_key_that_contains_a_dot(): void
    {
        $validator = $this->validator(['user.name' => '', 'user' => ['name' => 'Ana']], ['user\.name' => 'required']);

        $this->assertTrue($validator->fails());
        $this->assertSame(['user.name'], \array_keys($validator->failed()));
        $this->assertSame(['user.name'], $validator->errors()->keys());
    }

    public function test_an_escaped_dot_in_a_parameter_refers_to_the_dotted_key(): void
    {
        $data = ['pass.word' => 'secret', 'confirm' => 'secret'];

        $this->assertTrue($this->validator($data, ['confirm' => 'same:pass\.word'])->passes());
        $this->assertTrue(
            $this->validator(['pass.word' => 'a', 'confirm' => 'b'], ['confirm' => 'same:pass\.word'])->fails()
        );
    }

    public function test_sometimes_and_add_rules_keep_escaped_dots(): void
    {
        $validator = $this->validator(['a.b' => ''], []);
        $validator->sometimes('a\.b', 'required', static fn(): bool => true);

        $this->assertTrue($validator->fails());

        $validator = $this->validator(['a.b' => ''], []);
        $validator->addRules(['a\.b' => 'required']);

        $this->assertTrue($validator->fails());
    }

    public function test_numeric_first_level_keys_are_preserved(): void
    {
        $validated = $this->validator([10 => 'ten', 20 => 'twenty'], [10 => 'required', 20 => 'required'])->validated();

        $this->assertSame([10 => 'ten', 20 => 'twenty'], $validated);
    }

    private function validator(array $data, array $rules)
    {
        return $this->app->make(Factory::class)->make($data, $rules);
    }
}
