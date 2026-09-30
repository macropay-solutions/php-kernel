<?php

namespace MacropaySolutions\Kernel\Queue;

class WorkerOptions
{
    /**
     * Create a new worker options instance.
     * @param int|int[] $backoff
     */
    public function __construct(
        public string $name = 'default',
        public string $backoff = '0',
        public int $timeout = 60,
        public int $sleep = 3,
        public int $maxTries = 1,
        public bool $failOnFatal = true,
    ) {
    }
}
