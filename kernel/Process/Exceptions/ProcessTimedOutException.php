<?php

namespace MacropaySolutions\Kernel\Process\Exceptions;

use MacropaySolutions\Kernel\Contracts\Process\ProcessResult;
use Symfony\Component\Process\Exception\ProcessTimedOutException as SymfonyTimeoutException;
use Symfony\Component\Process\Exception\RuntimeException;

class ProcessTimedOutException extends RuntimeException
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
     * @param \Symfony\Component\Process\Exception\ProcessTimedOutException $original
     * @param \MacropaySolutions\Kernel\Contracts\Process\ProcessResult $result
     * @return void
     */
    public function __construct(SymfonyTimeoutException $original, ProcessResult $result)
    {
        $this->result = $result;

        parent::__construct($original->getMessage(), $original->getCode(), $original);
    }
}
