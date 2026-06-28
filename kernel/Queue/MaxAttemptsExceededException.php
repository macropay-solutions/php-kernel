<?php

namespace MacropaySolutions\Kernel\Queue;

use RuntimeException;

class MaxAttemptsExceededException extends RuntimeException
{
    /**
     * The job instance.
     *
     * @var \MacropaySolutions\Kernel\Contracts\Queue\Job|null
     */
    public $job;

    /**
     * Create a new instance for the job.
     *
     * @param \MacropaySolutions\Kernel\Contracts\Queue\Job $job
     * @return static
     */
    public static function forJob($job)
    {
        return tap(new static($job->resolveName() . ' has been attempted too many times.'), function ($e) use ($job) {
            $e->job = $job;
        });
    }
}
