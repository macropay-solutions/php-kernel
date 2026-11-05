<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures;

use MacropaySolutions\Kernel\Queue\NullQueue;

/**
 * A queue that keeps the payloads it receives instead of sending them anywhere.
 */
class PayloadQueue extends NullQueue
{
    /**
     * @var array<int, array>
     */
    public array $payloads = [];

    public function push($job, $data = '', $queue = null)
    {
        $this->payloads[] = $this->payloadFor($job, $queue, $data);

        return null;
    }

    public function later($delay, $job, $data = '', $queue = null)
    {
        return $this->push($job, $data, $queue);
    }

    public function payloadFor(mixed $job, ?string $queue = null, mixed $data = ''): array
    {
        return \json_decode($this->createPayload($job, $queue ?? 'default', $data), true);
    }
}
