<?php

namespace MacropaySolutions\Kernel\Tests\Feature\Bus;

use MacropaySolutions\Kernel\Bus\Dispatcher;
use MacropaySolutions\Kernel\Database\Schema\Blueprint;
use MacropaySolutions\Kernel\Queue\CallQueuedCallable;
use MacropaySolutions\Kernel\Tests\ApplicationTestCase;
use MacropaySolutions\Kernel\Tests\Fixtures\Bus\BusSteps;

/**
 * Chains and batches of array callables through the database queue and the worker.
 */
class ChainAndBatchTest extends ApplicationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        BusSteps::$log = [];
        $this->app->make('config')->set('queue.batching', ['database' => 'sqlite', 'table' => 'job_batches']);
        $this->db()->getSchemaBuilder()->create('job_batches', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });
    }

    public function test_a_chain_runs_its_steps_in_order_one_job_at_a_time(): void
    {
        CallQueuedCallable::create([BusSteps::class, 'record', ['step' => 'first']])
            ->chain([
                [BusSteps::class, 'record', ['step' => 'second']],
                [BusSteps::class, 'record', ['step' => 'third']],
            ])->dispatch();

        $this->work();
        $this->assertSame(['first'], BusSteps::$log);

        $this->work(2);
        $this->assertSame(['first', 'second', 'third'], BusSteps::$log);
        $this->assertSame(0, $this->db()->table('jobs')->count());
    }

    public function test_a_failing_step_stops_the_chain(): void
    {
        CallQueuedCallable::create([BusSteps::class, 'record', ['step' => 'first']])
            ->chain([
                [BusSteps::class, 'fail', ['step' => 'second']],
                [BusSteps::class, 'record', ['step' => 'third']],
            ])->dispatch();

        $this->work(3);

        $this->assertSame(['first', 'second'], BusSteps::$log);
        $this->assertSame(1, $this->db()->table('failed_jobs')->count());
        $this->assertSame(0, $this->db()->table('jobs')->count());
    }

    public function test_the_dispatcher_chain_is_refused(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->app->make(Dispatcher::class)->chain([[BusSteps::class, 'record', ['step' => 'x']]]);
    }

    public function test_a_batch_runs_its_jobs_then_its_then_and_finally_callbacks(): void
    {
        $batch = $this->app->make(Dispatcher::class)->batch([
            [BusSteps::class, 'record', ['step' => 'a']],
            [BusSteps::class, 'record', ['step' => 'b']],
        ])->then([BusSteps::class, 'then'])
            ->finally([BusSteps::class, 'finally'])
            ->name('import')
            ->dispatch();

        $this->assertSame(2, $this->db()->table('jobs')->count());

        $this->work(2);

        $this->assertSame(['a', 'b', 'then:2', 'finally:finished'], BusSteps::$log);

        $stored = $this->app->make(Dispatcher::class)->findBatch($batch->id);

        $this->assertSame('import', $stored->name);
        $this->assertSame(0, $stored->pendingJobs);
        $this->assertTrue($stored->finished());
    }

    public function test_a_failing_batch_job_calls_catch(): void
    {
        $this->app->make(Dispatcher::class)->batch([
            [BusSteps::class, 'fail', ['step' => 'a']],
        ])->catch([BusSteps::class, 'catch'])
            ->dispatch();

        $this->work();

        $this->assertSame(['a', 'catch:step a failed'], BusSteps::$log);
        $this->assertSame(1, $this->db()->table('failed_jobs')->count());
    }

    private function work(int $times = 1): void
    {
        for ($i = 0; $i < $times; $i++) {
            $this->artisan('queue:work', ['connection' => 'database', '--sleep' => 0]);
        }
    }
}
