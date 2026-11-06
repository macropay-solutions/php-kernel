<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Queue;

/**
 * Records what queue:retry passes to getQueueableOptions(), like SqsQueue receives it.
 */
class OptionsRecordingQueue
{
    public array $calls = [];

    public function getQueueableOptions(mixed $job, ?string $queue, mixed $delay = null): array
    {
        $this->calls[] = [$job, $queue, $delay];

        return [];
    }
}
