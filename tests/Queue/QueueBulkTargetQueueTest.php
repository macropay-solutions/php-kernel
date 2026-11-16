<?php

namespace MacropaySolutions\Kernel\Tests\Queue;

use MacropaySolutions\Kernel\Queue\Queue;
use PHPUnit\Framework\TestCase;

/**
 * bulk() without a queue pushed every job to the default queue and ignored its delay, e.g. the jobs and chains of a
 * batch without a queue option.
 */
class QueueBulkTargetQueueTest extends TestCase
{
    public function testEachJobKeepsItsQueueAndDelay(): void
    {
        $queue = $this->queue();
        $high = (object)['queue' => 'high'];
        $later = (object)['queue' => 'low', 'delay' => 30];
        $plain = (object)[];

        $queue->bulk([$high, $later, $plain]);

        $this->assertSame([['push', 'high'], ['later', 'low', 30], ['push', null]], $queue->calls);
    }

    public function testTheQueueOfTheBulkWins(): void
    {
        $queue = $this->queue();

        $queue->bulk([(object)['queue' => 'high']], '', 'batch');

        $this->assertSame([['push', 'batch']], $queue->calls);
    }

    private function queue(): Queue
    {
        return new class extends Queue {
            public array $calls = [];

            public function push($job, $data = '', $queue = null)
            {
                $this->calls[] = ['push', $queue];
            }

            public function later($delay, $job, $data = '', $queue = null)
            {
                $this->calls[] = ['later', $queue, $delay];
            }
        };
    }
}
