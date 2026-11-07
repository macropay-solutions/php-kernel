<?php

namespace MacropaySolutions\Kernel\Tests\Feature\Security;

use MacropaySolutions\Kernel\Contracts\Validation\Factory;
use MacropaySolutions\Kernel\Database\Schema\Blueprint;
use MacropaySolutions\Kernel\Tests\ApplicationTestCase;
use MacropaySolutions\Kernel\Validation\Rule;

/**
 * The unique/exists string syntax only takes a table and a column: ignored ids and extra wheres can only come
 * from Rule::unique() / Rule::exists(), so request data interpolated into a rule string cannot add conditions.
 * A rule that throws is recorded as a failure, so a refused rule string fails validation.
 */
class DatabaseRuleInjectionTest extends ApplicationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->db()->getSchemaBuilder()->create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('email');
            $table->string('status');
        });
        $this->db()->table('users')->insert([
            ['email' => 'ana@example.com', 'status' => 'active'],
            ['email' => 'ion@example.com', 'status' => 'banned'],
        ]);
    }

    public function test_the_two_part_string_syntax_still_works(): void
    {
        $this->assertTrue($this->passes(['email' => 'new@example.com'], ['email' => 'unique:users,email']));
        $this->assertFalse($this->passes(['email' => 'ana@example.com'], ['email' => 'unique:users,email']));
        $this->assertTrue($this->passes(['email' => 'ion@example.com'], ['email' => 'exists:users,email']));
    }

    public function test_an_ignored_id_in_the_unique_string_fails_the_rule_instead_of_ignoring_the_row(): void
    {
        // Laravel would ignore row 1 and pass; here the extended syntax throws and the rule fails.
        $validator = $this->app->make(Factory::class)->make(
            ['email' => 'ana@example.com'],
            ['email' => 'unique:users,email,1']
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('Unique', $validator->failed()['email']);
    }

    public function test_extra_wheres_in_the_exists_string_fail_the_rule(): void
    {
        // Laravel would add "status = active" and pass for ana; here the extended syntax is refused.
        $validator = $this->app->make(Factory::class)->make(
            ['email' => 'ana@example.com'],
            ['email' => 'exists:users,email,status,active']
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('Exists', $validator->failed()['email']);
    }

    public function test_rule_unique_can_still_ignore_an_id(): void
    {
        $rule = ['email' => [Rule::unique('users', 'email')->ignore(1)]];

        $this->assertTrue($this->passes(['email' => 'ana@example.com'], $rule));
        $this->assertFalse($this->passes(['email' => 'ion@example.com'], $rule));
    }

    public function test_rule_exists_can_still_add_wheres(): void
    {
        $rule = ['email' => [Rule::exists('users', 'email')->where('status', 'active')]];

        $this->assertTrue($this->passes(['email' => 'ana@example.com'], $rule));
        $this->assertFalse($this->passes(['email' => 'ion@example.com'], $rule));
    }

    private function passes(array $data, array $rules): bool
    {
        return $this->app->make(Factory::class)->make($data, $rules)->passes();
    }
}
