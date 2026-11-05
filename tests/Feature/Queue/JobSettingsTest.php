<?php

namespace MacropaySolutions\Kernel\Tests\Feature\Queue;

use MacropaySolutions\Kernel\Queue\CallQueuedCallable;
use MacropaySolutions\Kernel\Tests\Fixtures\Jobs\EncryptedPropertyJob;
use MacropaySolutions\Kernel\Tests\Fixtures\Jobs\HiddenSettingsJob;
use MacropaySolutions\Kernel\Tests\Fixtures\Jobs\PropertyBeatsMethodJob;
use MacropaySolutions\Kernel\Tests\Fixtures\Jobs\SettingsMethodsJob;
use MacropaySolutions\Kernel\Tests\Fixtures\Jobs\SettingsPropertiesJob;
use MacropaySolutions\Kernel\Tests\Fixtures\Jobs\UnusableSettingsMethodsJob;
use MacropaySolutions\Kernel\Tests\Fixtures\RestoringCallQueuedHandler;
use MacropaySolutions\Kernel\Tests\TestCase;

/**
 * Job settings of ::new()->dispatch() job classes are copied onto the queued CallQueuedCallable.
 */
class JobSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        SettingsPropertiesJob::$failures = [];
    }

    public function test_public_settings_reach_the_payload(): void
    {
        $payload = $this->queue->payloadFor(SettingsPropertiesJob::new(['podcastId' => 42]));

        $this->assertSame(5, $payload['maxTries']);
        $this->assertSame(120, $payload['timeout']);
        $this->assertSame(3, $payload['maxExceptions']);
        $this->assertSame('1,5,10', $payload['backoff']);
        $this->assertTrue($payload['failOnTimeout']);
        $this->assertSame(1900000000, $payload['retryUntil']);
    }

    public function test_setting_methods_are_used_when_no_property_is_set(): void
    {
        $payload = $this->queue->payloadFor(SettingsMethodsJob::new());

        $this->assertSame(4, $payload['maxTries']);
        $this->assertSame('1,5', $payload['backoff']);
    }

    public function test_a_public_property_wins_over_the_method(): void
    {
        $this->assertSame(7, PropertyBeatsMethodJob::new()->toStorableCallable()->tries);
    }

    public function test_non_public_settings_are_ignored(): void
    {
        $callable = HiddenSettingsJob::new()->toStorableCallable();

        $this->assertNull($callable->tries);
        $this->assertNull($callable->backoff);
        $this->assertFalse($callable->shouldBeEncrypted);
        $this->assertSame([], $callable->failureCallbacks);
    }

    public function test_setting_methods_that_need_arguments_or_throw_are_ignored(): void
    {
        $callable = UnusableSettingsMethodsJob::new()->toStorableCallable();

        $this->assertNull($callable->tries);
        $this->assertNull($callable->backoff);
        $this->assertNull($callable->retryUntil);
    }

    public function test_public_failed_method_receives_the_exception_and_the_job_arguments(): void
    {
        $payload = $this->queue->payloadFor(SettingsPropertiesJob::new(['podcastId' => 42]));
        $command = (new RestoringCallQueuedHandler($this->container))->restore($payload);

        $command->failed(new \RuntimeException('boom'));

        $this->assertSame([['boom', 42]], SettingsPropertiesJob::$failures);
    }

    public function test_public_should_be_encrypted_property_encrypts_the_payload(): void
    {
        $payload = $this->queue->payloadFor(EncryptedPropertyJob::new(['id' => 1]));

        $this->assertStringStartsNotWith('{', $payload['data']['command']);

        $command = (new RestoringCallQueuedHandler($this->container))->restore($payload);

        $this->assertSame([EncryptedPropertyJob::class, 'handle', ['id' => 1]], $command->storableCallable);
    }

    public function test_array_callables_do_not_read_class_settings(): void
    {
        $payload = $this->queue->payloadFor(CallQueuedCallable::create([PropertyBeatsMethodJob::class, 'handle']));

        $this->assertNull($payload['maxTries']);
        $this->assertStringStartsWith('{', $payload['data']['command']);
    }
}
