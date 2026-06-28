<?php

namespace MacropaySolutions\Kernel\Process;

use Countable;

class InvokedProcessPool implements Countable
{
    /**
     * The array of invoked processes.
     *
     * @var array
     */
    protected $invokedProcesses;

    /**
     * Create a new invoked process pool.
     *
     * @param array $invokedProcesses
     * @return void
     */
    public function __construct(array $invokedProcesses)
    {
        $this->invokedProcesses = $invokedProcesses;
    }

    /**
     * Send a signal to each running process in the pool, returning the processes that were signalled.
     *
     * @param int $signal
     * @return \MacropaySolutions\Kernel\Support\Collection
     */
    public function signal(int $signal)
    {
        return $this->running()->each->signal($signal);
    }

    /**
     * Get the processes in the pool that are still currently running.
     *
     * @return \MacropaySolutions\Kernel\Support\Collection
     */
    public function running()
    {
        return collect($this->invokedProcesses)->filter->running()->values();
    }

    /**
     * Wait for the processes to finish.
     *
     * @return \MacropaySolutions\Kernel\Process\ProcessPoolResults
     */
    public function wait()
    {
        return new ProcessPoolResults(collect($this->invokedProcesses)->map->wait()->all());
    }

    /**
     * Get the total number of processes.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->invokedProcesses);
    }
}
