<?php

namespace MacropaySolutions\Kernel\Tests\Feature\Database;

use MacropaySolutions\Kernel\Contracts\Console\Kernel as ConsoleKernelContract;
use MacropaySolutions\Kernel\Database\Obvious\Model;
use MacropaySolutions\Kernel\Database\Schema\Blueprint;
use MacropaySolutions\Kernel\Tests\ApplicationTestCase;
use MacropaySolutions\Kernel\Tests\Fixtures\Database\AuditLog;
use MacropaySolutions\Kernel\Tests\Fixtures\Database\PruneTestKernel;

/**
 * Prunable models delete the rows their prunable() query matches, directly and through model:prune.
 */
class PruningTest extends ApplicationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Model::unsetConnectionResolver();
        AuditLog::$pruned = [];
        $this->app->singleton(ConsoleKernelContract::class, PruneTestKernel::class);

        $this->db()->getSchemaBuilder()->create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('message');
            $table->dateTime('created_at');
        });
        $this->db()->table('audit_logs')->insert([
            ['message' => 'old-1', 'created_at' => '2025-05-01 00:00:00'],
            ['message' => 'new', 'created_at' => '2026-06-01 00:00:00'],
            ['message' => 'old-2', 'created_at' => '2025-12-31 23:59:59'],
        ]);
    }

    protected function tearDown(): void
    {
        Model::unsetConnectionResolver();

        parent::tearDown();
    }

    public function test_prune_all_deletes_the_matching_rows_and_calls_pruning_on_each(): void
    {
        $this->assertSame(2, (new AuditLog())->pruneAll(1));
        $this->assertSame([1, 3], AuditLog::$pruned);
        $this->assertSame(['new'], $this->db()->table('audit_logs')->pluck('message')->all());
    }

    public function test_the_model_prune_command_prunes_the_given_models(): void
    {
        $exitCode = $this->artisan('model:prune', ['--model' => [AuditLog::class]]);
        $output = $this->artisanOutput();

        $this->assertSame(0, $exitCode, $output);
        $this->assertSame(['new'], $this->db()->table('audit_logs')->pluck('message')->all());
        $this->assertStringContainsString(AuditLog::class . ' : 2 records', $output);
    }

    public function test_the_model_prune_command_can_pretend(): void
    {
        $this->artisan('model:prune', ['--model' => [AuditLog::class], '--pretend' => true]);

        $this->assertSame(3, $this->db()->table('audit_logs')->count());
        $this->assertStringContainsString(
            '2 [' . AuditLog::class . '] records will be pruned.',
            $this->artisanOutput()
        );
    }
}
