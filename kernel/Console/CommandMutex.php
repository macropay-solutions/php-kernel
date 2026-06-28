<?php

namespace MacropaySolutions\Kernel\Console;

interface CommandMutex
{
    /**
     * Attempt to obtain a command mutex for the given command.
     *
     * @param \MacropaySolutions\Kernel\Console\Command $command
     * @return bool
     */
    public function create($command);

    /**
     * Determine if a command mutex exists for the given command.
     *
     * @param \MacropaySolutions\Kernel\Console\Command $command
     * @return bool
     */
    public function exists($command);

    /**
     * Release the mutex for the given command.
     *
     * @param \MacropaySolutions\Kernel\Console\Command $command
     * @return bool
     */
    public function forget($command);
}
