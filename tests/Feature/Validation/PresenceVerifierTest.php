<?php

namespace MacropaySolutions\Kernel\Tests\Feature\Validation;

use MacropaySolutions\Framework\Application;
use MacropaySolutions\Kernel\Container\Container;
use MacropaySolutions\Kernel\Contracts\Validation\Factory;
use MacropaySolutions\Kernel\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;

/**
 * The validator gets the database presence verifier even when it is resolved before the database was first used,
 * as in a request that validates its input before any query.
 */
class PresenceVerifierTest extends TestCase
{
    protected function tearDown(): void
    {
        Container::setInstance(null);
        \restore_error_handler();
        \restore_exception_handler();

        parent::tearDown();
    }

    public function test_unique_and_exists_work_when_the_validator_was_resolved_first(): void
    {
        $app = new Application(\dirname(__DIR__, 3));
        $app->configure('database');
        $app->make('config')->set('database.default', 'sqlite');
        $app->make('config')->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $validation = $app->make(Factory::class);

        $app->make('db')->connection()->getSchemaBuilder()->create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('email');
        });
        $app->make('db')->connection()->table('users')->insert(['email' => 'taken@example.com']);

        $unique = ['email' => 'unique:users,email'];

        $this->assertTrue($validation->make(['email' => 'new@example.com'], $unique)->passes());
        $this->assertFalse($validation->make(['email' => 'taken@example.com'], $unique)->passes());
        $this->assertTrue($validation->make(['id' => 1], ['id' => 'exists:users,id'])->passes());
    }
}
