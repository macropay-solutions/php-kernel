<?php

namespace MacropaySolutions\Kernel\Queue;

class ListenerOptions extends WorkerOptions
{
    /**
     * The environment the worker should run in.
     *
     * @var string
     */
    public $environment;

    public int $memory = 128;

    /**
     * Create a new listener options instance.
     *
     * @param string $name
     * @param string|null $environment
     * @param string $backoff
     * @param int $memory
     * @param int $timeout
     * @param int $sleep
     * @param int $maxTries
     * @return void
     */
    public function __construct(
        $name = 'default',
        $environment = null,
        $backoff = 0,
        $memory = 128,
        $timeout = 300,
        $sleep = 3,
        $maxTries = 1,
    ) {
        $this->environment = $environment;
        $this->memory = $memory;

        parent::__construct($name, $backoff, $timeout, $sleep, $maxTries);
    }
}
