<?php

namespace MacropaySolutions\Kernel\Tests\Feature\Queue;

use Aws\Sqs\SqsClient;
use MacropaySolutions\Kernel\Queue\CallQueuedCallable;
use MacropaySolutions\Kernel\Queue\SqsQueue;
use MacropaySolutions\Kernel\Tests\Fixtures\Jobs\FifoMethodsJob;
use MacropaySolutions\Kernel\Tests\Fixtures\Jobs\FifoTarget;
use MacropaySolutions\Kernel\Tests\Fixtures\Jobs\PlainJob;
use MacropaySolutions\Kernel\Tests\TestCase;

/**
 * Message group and deduplication ID of object jobs survive the conversion to CallQueuedCallable.
 */
class FifoQueueTest extends TestCase
{
    public function test_message_group_and_deduplication_methods_are_copied(): void
    {
        $callable = FifoMethodsJob::new()->toStorableCallable();

        $this->assertSame('obj-group', $callable->messageGroup);
        $this->assertSame('obj-dedup', $callable->deduplicationId);
    }

    public function test_fluent_message_group_and_deduplication_id_are_copied(): void
    {
        $job = PlainJob::new();
        $job->onGroup('fluent-group');
        $job->withDeduplicationId('fluent-dedup');

        $callable = $job->toStorableCallable();

        $this->assertSame('fluent-group', $callable->messageGroup);
        $this->assertSame('fluent-dedup', $callable->deduplicationId);
    }

    public function test_sqs_fifo_options(): void
    {
        if (!\class_exists(SqsClient::class)) {
            $this->markTestSkipped('aws/aws-sdk-php is not installed.');
        }

        $queue = new SqsQueue(
            new SqsClient(['region' => 'us-east-1', 'version' => 'latest', 'credentials' => false]),
            'jobs.fifo'
        );

        $options = $queue->getQueueableOptions(FifoMethodsJob::new(), 'jobs.fifo');

        $this->assertSame('obj-group', $options['MessageGroupId']);
        $this->assertSame('obj-dedup', $options['MessageDeduplicationId']);

        $callable = CallQueuedCallable::create([FifoTarget::class, 'handle', ['id' => 7]]);
        $callable->withDeduplicationId('explicit');

        $options = $queue->getQueueableOptions($callable, 'jobs.fifo');

        $this->assertSame('static-group-7', $options['MessageGroupId']);
        $this->assertSame('explicit', $options['MessageDeduplicationId']);
    }
}
