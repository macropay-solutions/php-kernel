<?php

namespace MacropaySolutions\Kernel\Process\Exceptions;

use MacropaySolutions\Kernel\Contracts\Process\ProcessResult;
use RuntimeException;

class ProcessFailedException extends RuntimeException
{
    /**
     * The process result instance.
     *
     * @var \MacropaySolutions\Kernel\Contracts\Process\ProcessResult
     */
    public $result;

    /**
     * Create a new exception instance.
     *
     * @param \MacropaySolutions\Kernel\Contracts\Process\ProcessResult $result
     * @return void
     */
    public function __construct(ProcessResult $result)
    {
        $this->result = $result;

        $error = sprintf(
            'The command "%s" failed.' . "\n\nExit Code: %s",
            $result->command(),
            $result->exitCode(),
        );

        if (\is_string($output = $result->output()) && '' !== $output) {
            $error .= sprintf("\n\nOutput:\n================\n%s", $output);
        }

        if (\is_string($errorOutput = $result->errorOutput()) && '' !== $errorOutput) {
            $error .= sprintf("\n\nError Output:\n================\n%s", $errorOutput);
        }

        parent::__construct($error, $result->exitCode() ?? 1);
    }
}
