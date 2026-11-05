<?php

namespace MacropaySolutions\Kernel\Tests\Feature\Queue;

use MacropaySolutions\Kernel\Queue\Jobs\SyncJob;
use MacropaySolutions\Kernel\Queue\UniqueCallQueuedCallable;
use MacropaySolutions\Kernel\Tests\Fixtures\Jobs\ChargeUniqueJob;
use MacropaySolutions\Kernel\Tests\Fixtures\Jobs\FailingUniqueJob;
use MacropaySolutions\Kernel\Tests\Fixtures\Jobs\FifoTarget;
use MacropaySolutions\Kernel\Tests\Fixtures\Jobs\InvoiceUniqueJob;
use MacropaySolutions\Kernel\Tests\Fixtures\Jobs\LocksStoreUniqueJob;
use MacropaySolutions\Kernel\Tests\Fixtures\Jobs\MissingModelUniqueJob;
use MacropaySolutions\Kernel\Tests\Fixtures\Jobs\PlainJob;
use MacropaySolutions\Kernel\Tests\Fixtures\Jobs\PropertyBeatsViaUniqueJob;
use MacropaySolutions\Kernel\Tests\Fixtures\Jobs\UntilProcessingRedispatchingJob;
use MacropaySolutions\Kernel\Tests\Fixtures\Jobs\ViaUniqueJob;
use MacropaySolutions\Kernel\Tests\TestCase;

/**
 * Unique jobs are dispatched through the real PendingDispatch, bus dispatcher and array cache locks,
 * and processed by the real CallQueuedHandler through a SyncJob.
 */
class UniqueJobTest extends TestCase
{
    public function test_unique_payload_keys_do_not_leak_into_the_next_job(): void
    {
        $unique = $this->queue->payloadFor(UniqueCallQueuedCallable::create([FifoTarget::class, 'handle', ['id' => 1]]));
        $plain = $this->queue->payloadFor(PlainJob::new());

        $this->assertNotEmpty($unique['uniqueJobKey']);
        $this->assertArrayNotHasKey('uniqueJobKey', $plain);
    }

    public function test_instance_dispatched_unique_job_is_locked_with_the_key_the_worker_releases(): void
    {
        InvoiceUniqueJob::new()->dispatch();
        InvoiceUniqueJob::new()->dispatch();

        $this->assertCount(1, $this->queue->payloads);

        $payload = $this->queue->payloads[0];

        // What CallQueuedHandler does when the job is done
        $this->cache->store($payload['uniqueJobCacheStore'])->fwd()->lock($payload['uniqueJobKey'])->forceRelease();

        InvoiceUniqueJob::new()->dispatch();

        $this->assertCount(2, $this->queue->payloads);
    }

    public function test_jobs_with_the_same_unique_id_do_not_share_a_lock(): void
    {
        InvoiceUniqueJob::new()->dispatch();
        ChargeUniqueJob::new()->dispatch();

        $this->assertCount(2, $this->queue->payloads);
        $this->assertNotSame($this->queue->payloads[0]['uniqueJobKey'], $this->queue->payloads[1]['uniqueJobKey']);
    }

    public function test_unique_job_without_a_cache_store_uses_the_default_store(): void
    {
        InvoiceUniqueJob::new()->dispatch();

        $this->assertSame('array', $this->queue->payloads[0]['uniqueJobCacheStore']);
    }

    public function test_unique_job_lock_is_taken_in_the_configured_cache_store(): void
    {
        LocksStoreUniqueJob::new(['id' => 7])->dispatch();

        $payload = $this->queue->payloads[0];

        $this->assertSame('locks', $payload['uniqueJobCacheStore']);
        $this->assertTrue($payload['uniqueUntilProcessing']);
        $this->assertFalse($this->cache->store('locks')->fwd()->lock($payload['uniqueJobKey'])->get());
        $this->assertTrue($this->cache->store('array')->fwd()->lock($payload['uniqueJobKey'])->get());
    }

    public function test_unique_via_method_of_the_job_selects_the_cache_store(): void
    {
        ViaUniqueJob::new()->dispatch();
        ViaUniqueJob::new()->dispatch();

        $this->assertCount(1, $this->queue->payloads);

        $payload = $this->queue->payloads[0];

        $this->assertSame('locks', $payload['uniqueJobCacheStore']);
        $this->assertFalse($this->cache->store('locks')->fwd()->lock($payload['uniqueJobKey'])->get());
    }

    public function test_unique_cache_store_property_wins_over_unique_via(): void
    {
        PropertyBeatsViaUniqueJob::new()->dispatch();

        $this->assertSame('array', $this->queue->payloads[0]['uniqueJobCacheStore']);
    }

    public function test_worker_releases_the_lock_after_the_job_succeeds(): void
    {
        InvoiceUniqueJob::new()->dispatch();

        $job = $this->work($this->queue->payloads[0]);

        $this->assertTrue($job->isDeleted());

        InvoiceUniqueJob::new()->dispatch();

        $this->assertCount(2, $this->queue->payloads);
    }

    public function test_worker_releases_an_until_processing_lock_before_the_job_runs(): void
    {
        UntilProcessingRedispatchingJob::new()->dispatch();

        $this->work($this->queue->payloads[0]);

        // The job dispatched itself again from handle(), which only works without the lock
        $this->assertCount(2, $this->queue->payloads);
    }

    public function test_worker_releases_the_lock_when_the_job_fails(): void
    {
        FailingUniqueJob::new()->dispatch();

        $job = new SyncJob($this->container, \json_encode($this->queue->payloads[0]), 'sync', 'default');

        try {
            $job->fire();
            $this->fail('The job should have thrown.');
        } catch (\RuntimeException $e) {
            // What the worker does when the job has no attempts left
            $job->fail($e);
        }

        $this->assertTrue($job->hasFailed());

        FailingUniqueJob::new()->dispatch();

        $this->assertCount(2, $this->queue->payloads);
    }

    public function test_worker_releases_the_lock_from_the_payload_when_a_model_is_missing(): void
    {
        MissingModelUniqueJob::new()->dispatch();

        $job = $this->work($this->queue->payloads[0]);

        $this->assertTrue($job->isDeleted());
        $this->assertFalse($job->hasFailed());

        MissingModelUniqueJob::new()->dispatch();

        $this->assertCount(2, $this->queue->payloads);
    }

    private function work(array $payload): SyncJob
    {
        $job = new SyncJob($this->container, \json_encode($payload), 'sync', 'default');
        $job->fire();

        return $job;
    }
}
