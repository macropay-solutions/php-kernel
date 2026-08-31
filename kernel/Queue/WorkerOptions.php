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
        public int|array $backoff = 0,
        public int $memory = 128,
        public int $timeout = 60,
        public int $sleep = 3,
        public int $maxTries = 1,
        public bool $force = false,
        public bool $stopWhenEmpty = false,
        public int $maxJobs = 0,
        public int $maxTime = 0,
        public int $rest = 0,
        public bool $failOnFatal = false,
    ) {
    }
}
