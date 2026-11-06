<?php

namespace MacropaySolutions\Kernel\Tests\Feature\Validation;

use MacropaySolutions\Kernel\Contracts\Validation\Factory;
use MacropaySolutions\Kernel\Database\Schema\Blueprint;
use MacropaySolutions\Kernel\Tests\ApplicationTestCase;
use MacropaySolutions\Kernel\Validation\Rule;
use MacropaySolutions\Kernel\Validation\ValidationException;
use MacropaySolutions\Kernel\Validation\Validator;

/**
 * The validator resolved from the application, with its real translator and database presence verifier.
 */
class ValidatorTest extends ApplicationTestCase
{
    public function test_passing_data_returns_only_the_validated_keys(): void
    {
        $validated = $this->validator(
            ['name' => 'Ana', 'age' => '30', 'extra' => 'ignored'],
            ['name' => 'required|string', 'age' => 'required|integer|min:18']
        )->validate();

        $this->assertSame(['name' => 'Ana', 'age' => '30'], $validated);
    }

    public function test_failing_data_reports_translated_messages_per_field(): void
    {
        $validator = $this->validator(
            ['email' => 'not-an-email', 'age' => '12'],
            ['name' => 'required', 'email' => 'email', 'age' => 'integer|min:18']
        );

        $this->assertTrue($validator->fails());
        $this->assertSame(
            [
                'name' => ['The name field is required.'],
                'email' => ['The email must be a valid email address.'],
                'age' => ['The age must be at least 18.'],
            ],
            $validator->errors()->toArray()
        );
    }

    public function test_validate_throws_a_validation_exception_with_status_422(): void
    {
        try {
            $this->validator([], ['name' => 'required'])->validate();
            $this->fail('A ValidationException was expected.');
        } catch (ValidationException $e) {
            $this->assertSame(422, $e->status);
            $this->assertSame(['name' => ['The name field is required.']], $e->errors());
        }
    }

    public function test_nested_array_rules_and_validated_output(): void
    {
        $data = [
            'items' => [
                ['sku' => 'A1', 'qty' => 2, 'note' => 'drop'],
                ['sku' => 'B2', 'qty' => 0],
            ],
        ];
        $rules = [
            'items' => 'required|array|min:1',
            'items.*.sku' => 'required|string',
            'items.*.qty' => 'integer|min:1',
        ];

        $validator = $this->validator($data, $rules);

        $this->assertSame(
            ['items.1.qty' => ['The items.1.qty must be at least 1.']],
            $validator->errors()->toArray()
        );

        $data['items'][1]['qty'] = 3;

        $this->assertSame(
            ['items' => [['sku' => 'A1', 'qty' => 2], ['sku' => 'B2', 'qty' => 3]]],
            $this->validator($data, $rules)->validate()
        );
    }

    public function test_bail_stops_at_the_first_failing_rule(): void
    {
        $validator = $this->validator(['code' => 'x'], ['code' => 'bail|integer|min:5']);

        $this->assertSame(['code' => ['The code must be an integer.']], $validator->errors()->toArray());
    }

    public function test_conditional_rules(): void
    {
        $rules = [
            'type' => 'required|in:company,person',
            'vat' => 'required_if:type,company',
            'birthday' => 'exclude_unless:type,person|required|date',
        ];

        $this->assertTrue($this->validator(['type' => 'person', 'birthday' => '2000-01-01'], $rules)->passes());
        $this->assertSame(
            ['vat' => ['The vat field is required when type is company.']],
            $this->validator(['type' => 'company', 'birthday' => 'nonsense'], $rules)->errors()->toArray()
        );
        $this->assertSame(
            ['type' => 'company', 'vat' => 'RO1'],
            $this->validator(['type' => 'company', 'vat' => 'RO1', 'birthday' => 'x'], $rules)->validate()
        );
    }

    public function test_custom_messages_and_attribute_names(): void
    {
        $validator = $this->validator(
            ['first_name' => ''],
            ['first_name' => 'required', 'last_name' => 'required'],
            ['last_name.required' => 'We need your :attribute.'],
            ['first_name' => 'given name', 'last_name' => 'family name']
        );

        $this->assertSame(
            [
                'first_name' => ['The given name field is required.'],
                'last_name' => ['We need your family name.'],
            ],
            $validator->errors()->toArray()
        );
    }

    public function test_rule_objects_and_closures(): void
    {
        $validator = $this->validator(
            ['status' => 'archived', 'code' => 'abc'],
            [
                'status' => [Rule::in(['draft', 'published'])],
                'code' => [function (string $attribute, mixed $value, \Closure $fail): void {
                    if (\strtoupper($value) !== $value) {
                        $fail("The {$attribute} must be uppercase.");
                    }
                }],
            ]
        );

        $this->assertSame(
            ['status' => ['The selected status is invalid.'], 'code' => ['The code must be uppercase.']],
            $validator->errors()->toArray()
        );
    }

    public function test_after_hooks_can_add_errors(): void
    {
        $validator = $this->validator(['from' => 5, 'to' => 3], ['from' => 'integer', 'to' => 'integer']);
        $validator->after(function (Validator $validator): void {
            if ($validator->getData()['from'] > $validator->getData()['to']) {
                $validator->errors()->add('to', 'The range is reversed.');
            }
        });

        $this->assertSame(['to' => ['The range is reversed.']], $validator->errors()->toArray());
    }

    public function test_unique_and_exists_query_the_database(): void
    {
        $this->db()->getSchemaBuilder()->create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('email');
            $table->boolean('active');
        });
        $this->db()->table('users')->insert([
            ['email' => 'taken@example.com', 'active' => 1],
            ['email' => 'inactive@example.com', 'active' => 0],
        ]);

        $this->assertSame(
            ['email' => ['The email has already been taken.']],
            $this->validator(['email' => 'taken@example.com'], ['email' => 'unique:users,email'])
                ->errors()
                ->toArray()
        );
        $this->assertTrue(
            $this->validator(['email' => 'new@example.com'], ['email' => 'unique:users,email'])->passes()
        );
        $this->assertTrue(
            $this->validator(['email' => 'taken@example.com'], ['email' => Rule::unique('users')->ignore(1)])->passes()
        );
        $this->assertTrue($this->validator(['user_id' => 1], ['user_id' => 'exists:users,id'])->passes());
        $activeUser = [Rule::exists('users', 'id')->where('active', 1)];

        $this->assertFalse($this->validator(['user_id' => 2], ['user_id' => $activeUser])->passes());
    }

    private function validator(array $data, array $rules, array $messages = [], array $attributes = []): Validator
    {
        return $this->app->make(Factory::class)->make($data, $rules, $messages, $attributes);
    }
}
