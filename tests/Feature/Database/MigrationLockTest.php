<?php

namespace MacropaySolutions\Kernel\Tests\Feature\Database;

use MacropaySolutions\Kernel\Database\Console\Migrations\MigrateCommand;
use MacropaySolutions\Kernel\Tests\ApplicationTestCase;

/**
 * migrate and migrate:rollback take a cache lock, so two deploys cannot migrate the same database at once.
 */
class MigrationLockTest extends ApplicationTestCase
{
    private const PARAMETERS = [
        '--path' => __DIR__ . '/../../Fixtures/Migrations',
        '--realpath' => true,
        '--force' => true,
    ];

    public function test_migrate_and_rollback_run_when_the_lock_is_free(): void
    {
        $this->assertSame(0, $this->artisan('migrate', self::PARAMETERS), $this->artisanOutput());
        $this->assertTrue($this->db()->getSchemaBuilder()->hasTable('gadgets'));

        $this->assertSame(0, $this->artisan('migrate:rollback', self::PARAMETERS), $this->artisanOutput());
        $this->assertFalse($this->db()->getSchemaBuilder()->hasTable('gadgets'));
    }

    public function test_migrate_refuses_to_run_while_another_process_holds_the_lock(): void
    {
        $lock = $this->migrationLock();
        $lock->get();

        $this->assertSame(1, $this->artisan('migrate', self::PARAMETERS));
        $this->assertStringContainsString('Could not acquire lock for migrate command.', $this->artisanOutput());
        $this->assertFalse($this->db()->getSchemaBuilder()->hasTable('gadgets'));

        $lock->release();
    }

    public function test_rollback_refuses_to_run_while_another_process_holds_the_lock(): void
    {
        $this->artisan('migrate', self::PARAMETERS);
        $lock = $this->migrationLock();
        $lock->get();

        $this->assertSame(1, $this->artisan('migrate:rollback', self::PARAMETERS));
        $this->assertStringContainsString(
            'Could not acquire lock for migrate:rollback command.',
            $this->artisanOutput()
        );
        $this->assertTrue($this->db()->getSchemaBuilder()->hasTable('gadgets'));

        $lock->release();
    }

    public function test_the_lock_is_released_after_migrating(): void
    {
        $this->artisan('migrate', self::PARAMETERS);

        $this->assertTrue($this->migrationLock()->get());
    }

    private function migrationLock(): \MacropaySolutions\Kernel\Contracts\Cache\Lock
    {
        return $this->app->make('cache')->store()->fwd()->lock(MigrateCommand::FRAMEWORK_MIGRATE_LOCK, 300);
    }
}
