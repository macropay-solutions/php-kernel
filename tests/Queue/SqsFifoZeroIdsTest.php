<?php

namespace MacropaySolutions\Kernel\Tests\Queue;

use Aws\Sqs\SqsClient;
use MacropaySolutions\Kernel\Queue\SqsQueue;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * "0" is a valid FIFO message group and deduplication ID; it was replaced by the queue name or dropped.
 */
class SqsFifoZeroIdsTest extends TestCase
{
    public function testZeroIdsAreKept(): void
    {
        $queue = new SqsQueue($this->createStub(SqsClient::class), 'jobs.fifo');
        $job = new stdClass();
        $job->messageGroup = '0';
        $job->deduplicationId = '0';

        $this->assertSame(
            ['MessageGroupId' => '0', 'MessageDeduplicationId' => '0'],
            $queue->getQueueableOptions($job, 'jobs.fifo')
        );
    }

    public function testEmptyGroupFallsBackToTheQueueName(): void
    {
        $queue = new SqsQueue($this->createStub(SqsClient::class), 'jobs.fifo');
        $job = new stdClass();
        $job->messageGroup = '';
        $job->deduplicationId = 'dedup';

        $this->assertSame(
            ['MessageGroupId' => 'jobs.fifo', 'MessageDeduplicationId' => 'dedup'],
            $queue->getQueueableOptions($job, 'jobs.fifo')
        );
    }
}
