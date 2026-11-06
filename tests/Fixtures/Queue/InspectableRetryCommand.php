<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Queue;

use MacropaySolutions\Kernel\Queue\Console\RetryCommand;

class InspectableRetryCommand extends RetryCommand
{
    public function optionsFor(object $queue, \stdClass $job): array
    {
        return $this->getQueueableOptions($queue, $job);
    }
}
