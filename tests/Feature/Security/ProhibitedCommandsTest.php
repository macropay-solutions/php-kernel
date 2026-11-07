<?php

namespace MacropaySolutions\Kernel\Tests\Feature\Security;

use MacropaySolutions\Kernel\Database\Console\Migrations\FreshCommand;
use MacropaySolutions\Kernel\Database\Console\Migrations\RefreshCommand;
use MacropaySolutions\Kernel\Database\Console\Migrations\ResetCommand;
use MacropaySolutions\Kernel\Database\Schema\Blueprint;
use MacropaySolutions\Kernel\Tests\ApplicationTestCase;

/**
 * Destructive migration commands are prohibited in production by default, even with --force.
 */
class ProhibitedCommandsTest extends ApplicationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if ($this->app->environment() !== 'production') {
            $this->markTestSkipped('The tests run in the default (production) environment.');
        }

        $this->db()->getSchemaBuilder()->create('payments', function (Blueprint $table): void {
            $table->id();
        });
        $this->db()->table('payments')->insert(['id' => 1]);
    }

    protected function tearDown(): void
    {
        FreshCommand::prohibit();
        RefreshCommand::prohibit();
        ResetCommand::prohibit();

        parent::tearDown();
    }

    public function test_destructive_migration_commands_refuse_to_run_in_production(): void
    {
        foreach (['migrate:fresh', 'migrate:refresh', 'migrate:reset'] as $command) {
            $exitCode = $this->artisan($command, ['--force' => true]);

            $this->assertNotSame(0, $exitCode, $command);
            $this->assertStringContainsString('prohibited from running', $this->artisanOutput(), $command);
        }

        $this->assertSame(1, $this->db()->table('payments')->count());
    }

    public function test_the_prohibition_can_be_lifted_explicitly(): void
    {
        FreshCommand::prohibit(false);

        $this->artisan('migrate:fresh', ['--force' => true]);

        $this->assertStringNotContainsString('prohibited from running', $this->artisanOutput());
    }
}
