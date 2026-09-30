<?php

namespace MacropaySolutions\Kernel\Queue;

use MacropaySolutions\Kernel\Console\Application;
use MacropaySolutions\Kernel\Contracts\Cache\Repository as CacheContract;
use MacropaySolutions\Kernel\Contracts\Debug\ExceptionHandler;
use MacropaySolutions\Kernel\Contracts\Events\Dispatcher;
use MacropaySolutions\Kernel\Contracts\Queue\Factory as QueueManager;
use MacropaySolutions\Kernel\Contracts\Queue\Job;
use MacropaySolutions\Kernel\Database\DetectsLostConnections;
use MacropaySolutions\Kernel\Queue\Events\JobExceptionOccurred;
use MacropaySolutions\Kernel\Queue\Events\JobPopped;
use MacropaySolutions\Kernel\Queue\Events\JobPopping;
use MacropaySolutions\Kernel\Queue\Events\JobProcessed;
use MacropaySolutions\Kernel\Queue\Events\JobProcessing;
use MacropaySolutions\Kernel\Queue\Events\JobReleasedAfterException;
use MacropaySolutions\Kernel\Queue\Events\JobTimedOut;
use MacropaySolutions\Kernel\Queue\Events\WorkerStopping;
use MacropaySolutions\Kernel\Support\Carbon;
use MacropaySolutions\Kernel\Support\ProcessUtils;
use Throwable;

class Worker
{
    use DetectsLostConnections;

    public const EXIT_ERROR = 1;

    /**
     * The name of the worker.
     *
     * @var string
     */
    protected $name;

    /**
     * The queue manager instance.
     *
     * @var \MacropaySolutions\Kernel\Contracts\Queue\Factory
     */
    protected $manager;

    /**
     * The event dispatcher instance.
     *
     * @var \MacropaySolutions\Kernel\Contracts\Events\Dispatcher
     */
    protected $events;

    /**
     * The cache repository implementation.
     *
     * @var \MacropaySolutions\Kernel\Contracts\Cache\Repository
     */
    protected $cache;

    /**
     * The exception handler instance.
     *
     * @var \MacropaySolutions\Kernel\Contracts\Debug\ExceptionHandler
     */
    protected $exceptions;

    /**
     * The callbacks used to pop jobs from queues.
     *
     * @var callable[]
     */
    protected static $popCallbacks = [];

    /**
     * Used for caching the job details so it can be marked as failed and deleted through
     * @see FailJobCommand::class
     * in case of php error
     * Can contain one cache key and Job instance as value.
     */
    protected array $snapshotForFailJobInBgDueToPhpError = [];

    protected bool $extensionLoadedPcntl = false;

    /**
     * Create a new queue worker.
     *
     * @param \MacropaySolutions\Kernel\Contracts\Queue\Factory $manager
     * @param \MacropaySolutions\Kernel\Contracts\Events\Dispatcher $events
     * @param \MacropaySolutions\Kernel\Contracts\Debug\ExceptionHandler $exceptions
     * @return void
     */
    public function __construct(
        QueueManager $manager,
        Dispatcher $events,
        ExceptionHandler $exceptions,
    ) {
        $this->events = $events;
        $this->manager = $manager;
        $this->exceptions = $exceptions;
        $this->extensionLoadedPcntl = \extension_loaded('pcntl');
    }

    /**
     * Register the worker timeout handler.
     *
     * @param Job|null $job
     * @param \MacropaySolutions\Kernel\Queue\WorkerOptions $options
     * @return void
     */
    protected function registerTimeoutHandler($job, WorkerOptions $options)
    {
        // We will register a signal handler for the alarm signal so that we can kill this
        // process if it is running too long because it has frozen. This uses the async
        // signals supported in recent versions of PHP to accomplish it conveniently.
        pcntl_signal(SIGALRM, function () use ($job, $options) {
            if ($job) {
                $this->markJobAsFailedIfWillExceedMaxAttempts(
                    $job->getConnectionName(),
                    $job,
                    (int)$options->maxTries,
                    $e = $this->timeoutExceededException($job)
                );

                $this->markJobAsFailedIfWillExceedMaxExceptions(
                    $job->getConnectionName(),
                    $job,
                    $e
                );

                $this->markJobAsFailedIfItShouldFailOnTimeout(
                    $job->getConnectionName(),
                    $job,
                    $e
                );

                $this->events->dispatch(
                    new JobTimedOut(
                        $job->getConnectionName(),
                        $job
                    )
                );
            }

            $this->kill(static::EXIT_ERROR, $options);
        }, true);

        pcntl_alarm(
            max($this->timeoutForJob($job, $options), 0)
        );
    }

    /**
     * Reset the worker timeout handler.
     *
     * @return void
     */
    protected function resetTimeoutHandler()
    {
        pcntl_alarm(0);
    }

    /**
     * Get the appropriate timeout for the given job.
     *
     * @param Job|null $job
     * @param \MacropaySolutions\Kernel\Queue\WorkerOptions $options
     * @return int
     */
    protected function timeoutForJob($job, WorkerOptions $options)
    {
        return $job && !is_null($job->timeout()) ? $job->timeout() : $options->timeout;
    }

    /**
     * Process the next job on the queue.
     *
     * @param string $connectionName
     * @param string $queue
     * @param \MacropaySolutions\Kernel\Queue\WorkerOptions $options
     * @return void
     */
    public function runNextJob($connectionName, $queue, WorkerOptions $options)
    {
        $this->snapshotForFailJobInBgDueToPhpError = [];
        $this->registerPhpErrorCallback($options, $queue, $connectionName);

        $job = $this->getNextJob(
            $this->manager->connection($connectionName),
            $queue
        );

        // If we're able to pull a job off of the stack, we will process it and then return
        // from this method. If there is no job on the queue, we will "sleep" the worker
        // for the specified number of seconds, then keep processing jobs after sleep.
        if ($job) {
            if ($this->extensionLoadedPcntl) {
                \pcntl_async_signals(true);
                $this->registerTimeoutHandler($job, $options);
            }

            $this->runJob($job, $connectionName, $options);

            if ($this->extensionLoadedPcntl) {
                $this->resetTimeoutHandler();
            }

            return;
        }

        $this->sleep($options->sleep);
    }

    /**
     * Delete and fail the job on the queue.
     * @throws \Throwable
     */
    public function deleteAndFailJob(
        string $connectionName,
        string $queue,
        string $key,
        string $error
    ): void {
        try {
            /**
             * @see \MacropaySolutions\Kernel\Queue\Worker::snapshotJobInCaseOfPhpError()
             */
            $this->snapshotForFailJobInBgDueToPhpError = [$key => null];
            $cachedJob = $this->cache->get($key);

            if (!\is_array($cachedJob)) {
                throw new \Exception(__FUNCTION__ . ' aborted: cached job missing for key ' .  $key);
            }

            $connection = $this->manager->connection($connectionName);

            if (\method_exists($connection, 'getJobToBeFailedFromCachedData')) {
                $job = $connection->getJobToBeFailedFromCachedData($cachedJob, $queue);
            }

            if (($job ?? null) instanceof Job) {
                $this->failJob($job, new \Exception($error));
            }
        } catch (Throwable $e) {
            $this->exceptions->report($e);
        } finally {
            $this->resetSnapshotJobInCaseOfPhpError();
        }
    }

    /**
     * Get the next job from the queue connection.
     *
     * @param \MacropaySolutions\Kernel\Contracts\Queue\Queue $connection
     * @param string $queue
     * @return Job|null
     */
    protected function getNextJob($connection, $queue)
    {
        $popJobCallback = function ($queue) use ($connection) {
            return $connection->pop($queue);
        };

        $this->raiseBeforeJobPopEvent($connection->getConnectionName());

        try {
            if (isset(static::$popCallbacks[$this->name])) {
                return tap(
                    (static::$popCallbacks[$this->name])($popJobCallback, $queue),
                    fn($job) => $this->raiseAfterJobPopEvent($connection->getConnectionName(), $job)
                );
            }

            foreach (explode(',', $queue) as $queue) {
                if (!is_null($job = $popJobCallback($queue))) {
                    $this->raiseAfterJobPopEvent($connection->getConnectionName(), $job);

                    return $job;
                }
            }
        } catch (Throwable $e) {
            $this->exceptions->report($e);

            $this->sleep(1);
        }
    }

    /**
     * Process the given job.
     *
     * @param Job $job
     * @param string $connectionName
     * @param \MacropaySolutions\Kernel\Queue\WorkerOptions $options
     * @return void
     */
    protected function runJob($job, $connectionName, WorkerOptions $options)
    {
        try {
            $this->process($connectionName, $job, $options);
        } catch (Throwable $e) {
            $this->exceptions->report($e);
        }
    }

    /**
     * Process the given job from the queue.
     *
     * @param string $connectionName
     * @param Job $job
     * @param \MacropaySolutions\Kernel\Queue\WorkerOptions $options
     * @return void
     *
     * @throws \Throwable
     */
    public function process($connectionName, $job, WorkerOptions $options)
    {
        try {
            // First we will raise the before job event and determine if the job has already run
            // over its maximum attempt limits, which could primarily happen when this job is
            // continually timing out and not actually throwing any exceptions from itself.
            $this->raiseBeforeJobEvent($connectionName, $job);

            $this->markJobAsFailedIfAlreadyExceedsMaxAttempts(
                $connectionName,
                $job,
                (int)$options->maxTries
            );

            if ($job->isDeleted()) {
                $this->raiseAfterJobEvent($connectionName, $job);

                return;
            }

            if ($options->failOnFatal || '0' === (string)($job->maxTries() ?? $options->maxTries)) {
                $this->snapshotJobInCaseOfPhpError($job);
            }

            // Here we will fire off the job and let it process. We will catch any exceptions, so
            // they can be reported to the developer's logs, etc. Once the job is finished the
            // proper events will be fired to let any listeners know this job has completed.
            $job->fire();
            $this->resetSnapshotJobInCaseOfPhpError();

            $this->raiseAfterJobEvent($connectionName, $job);
        } catch (Throwable $e) {
            $this->handleJobException($connectionName, $job, $options, $e);
        } finally {
            $this->resetSnapshotJobInCaseOfPhpError();
        }
    }

    /**
     * Handle an exception that occurred while the job was running.
     *
     * @param string $connectionName
     * @param Job $job
     * @param \MacropaySolutions\Kernel\Queue\WorkerOptions $options
     * @param \Throwable $e
     * @return void
     *
     * @throws \Throwable
     */
    protected function handleJobException($connectionName, $job, WorkerOptions $options, Throwable $e)
    {
        try {
            // First, we will go ahead and mark the job as failed if it will exceed the maximum
            // attempts it is allowed to run the next time we process it. If so we will just
            // go ahead and mark it as failed now so we do not have to release this again.
            if (!$job->hasFailed()) {
                $this->markJobAsFailedIfWillExceedMaxAttempts(
                    $connectionName,
                    $job,
                    (int)$options->maxTries,
                    $e
                );

                $this->markJobAsFailedIfWillExceedMaxExceptions(
                    $connectionName,
                    $job,
                    $e
                );
            }

            $this->raiseExceptionOccurredJobEvent(
                $connectionName,
                $job,
                $e
            );
        } finally {
            // If we catch an exception, we will attempt to release the job back onto the queue
            // so it is not lost entirely. This'll let the job be retried at a later time by
            // another listener (or this same one). We will re-throw this exception after.
            if (!$job->isDeleted() && !$job->isReleased() && !$job->hasFailed()) {
                $job->release($this->calculateBackoff($job, $options));

                $this->events->dispatch(
                    new JobReleasedAfterException(
                        $connectionName,
                        $job
                    )
                );
            }
        }

        throw $e;
    }

    /**
     * Mark the given job as failed if it has exceeded the maximum allowed attempts.
     *
     * This will likely be because the job previously exceeded a timeout.
     *
     * @param string $connectionName
     * @param Job $job
     * @param int $maxTries
     * @return void
     *
     * @throws \Throwable
     */
    protected function markJobAsFailedIfAlreadyExceedsMaxAttempts($connectionName, $job, $maxTries)
    {
        $maxTries = !is_null($job->maxTries()) ? $job->maxTries() : $maxTries;

        $retryUntil = $job->retryUntil();

        if ($retryUntil && Carbon::now()->getTimestamp() <= $retryUntil) {
            return;
        }

        if (!$retryUntil && ($maxTries === 0 || $job->attempts() <= $maxTries)) {
            return;
        }

        $this->failJob($job, $e = $this->maxAttemptsExceededException($job));

        throw $e;
    }

    /**
     * Mark the given job as failed if it has exceeded the maximum allowed attempts.
     *
     * @param string $connectionName
     * @param Job $job
     * @param int $maxTries
     * @param \Throwable $e
     * @return void
     */
    protected function markJobAsFailedIfWillExceedMaxAttempts($connectionName, $job, $maxTries, Throwable $e)
    {
        $maxTries = !is_null($job->maxTries()) ? $job->maxTries() : $maxTries;

        if ($job->retryUntil() && $job->retryUntil() <= Carbon::now()->getTimestamp()) {
            $this->failJob($job, $e);
        }

        if (!$job->retryUntil() && $maxTries > 0 && $job->attempts() >= $maxTries) {
            $this->failJob($job, $e);
        }
    }

    /**
     * Mark the given job as failed if it has exceeded the maximum allowed attempts.
     *
     * @param string $connectionName
     * @param Job $job
     * @param \Throwable $e
     * @return void
     */
    protected function markJobAsFailedIfWillExceedMaxExceptions($connectionName, $job, Throwable $e)
    {
        if (
            !isset($this->cache)
            || (null === $uuid = $job->uuid())
            || (null === $maxExceptions = $job->maxExceptions())
        ) {
            return;
        }

        if (
            $maxExceptions <= (
                $this->cache->add($key = 'job-exceptions:' . $uuid, 1, Carbon::now()->addDay()) ?
                    1 :
                    $this->cache->increment($key)
            )
        ) {
            $this->cache->forget($key);

            $this->failJob($job, $e);
        }
    }

    /**
     * Mark the given job as failed if it should fail on timeouts.
     *
     * @param string $connectionName
     * @param Job $job
     * @param \Throwable $e
     * @return void
     */
    protected function markJobAsFailedIfItShouldFailOnTimeout($connectionName, $job, Throwable $e)
    {
        if (method_exists($job, 'shouldFailOnTimeout') ? $job->shouldFailOnTimeout() : false) {
            $this->failJob($job, $e);
        }
    }

    /**
     * Mark the given job as failed and raise the relevant event.
     *
     * @param Job $job
     * @param \Throwable $e
     * @return void
     */
    protected function failJob($job, Throwable $e)
    {
        $job->fail($e);
    }

    /**
     * Calculate the backoff for the given job.
     *
     * @param Job $job
     * @param \MacropaySolutions\Kernel\Queue\WorkerOptions $options
     * @return int
     */
    protected function calculateBackoff($job, WorkerOptions $options)
    {
        $backoff = explode(
            ',',
            method_exists($job, 'backoff') && !is_null($job->backoff())
                ? $job->backoff()
                : $options->backoff
        );

        return (int)($backoff[$job->attempts() - 1] ?? last($backoff));
    }

    /**
     * Raise the before job has been popped.
     *
     * @param string $connectionName
     * @return void
     */
    protected function raiseBeforeJobPopEvent($connectionName)
    {
        $this->events->dispatch(new JobPopping($connectionName));
    }

    /**
     * Raise the after job has been popped.
     *
     * @param string $connectionName
     * @param Job|null $job
     * @return void
     */
    protected function raiseAfterJobPopEvent($connectionName, $job)
    {
        $this->events->dispatch(
            new JobPopped(
                $connectionName,
                $job
            )
        );
    }

    /**
     * Raise the before queue job event.
     *
     * @param string $connectionName
     * @param Job $job
     * @return void
     */
    protected function raiseBeforeJobEvent($connectionName, $job)
    {
        $this->events->dispatch(
            new JobProcessing(
                $connectionName,
                $job
            )
        );
    }

    /**
     * Raise the after queue job event.
     *
     * @param string $connectionName
     * @param Job $job
     * @return void
     */
    protected function raiseAfterJobEvent($connectionName, $job)
    {
        $this->events->dispatch(
            new JobProcessed(
                $connectionName,
                $job
            )
        );
    }

    /**
     * Raise the exception occurred queue job event.
     *
     * @param string $connectionName
     * @param Job $job
     * @param \Throwable $e
     * @return void
     */
    protected function raiseExceptionOccurredJobEvent($connectionName, $job, Throwable $e)
    {
        $this->events->dispatch(
            new JobExceptionOccurred(
                $connectionName,
                $job,
                $e
            )
        );
    }

    /**
     * Kill the process.
     *
     * @param int $status
     * @param \MacropaySolutions\Kernel\Queue\WorkerOptions|null $options
     * @return never
     */
    public function kill($status = 0, $options = null)
    {
        $this->events->dispatch(new WorkerStopping($status, $options));

        if (extension_loaded('posix')) {
            posix_kill(getmypid(), SIGKILL);
        }

        exit($status);
    }

    /**
     * Create an instance of MaxAttemptsExceededException.
     *
     * @param Job $job
     * @return \MacropaySolutions\Kernel\Queue\MaxAttemptsExceededException
     */
    protected function maxAttemptsExceededException($job)
    {
        return MaxAttemptsExceededException::forJob($job);
    }

    /**
     * Create an instance of TimeoutExceededException.
     *
     * @param Job $job
     * @return \MacropaySolutions\Kernel\Queue\TimeoutExceededException
     */
    protected function timeoutExceededException($job)
    {
        return TimeoutExceededException::forJob($job);
    }

    /**
     * Sleep the script for a given number of seconds.
     *
     * @param int|float $seconds
     * @return void
     */
    public function sleep($seconds)
    {
        if ($seconds < 1) {
            usleep($seconds * 1000000);
        } else {
            sleep($seconds);
        }
    }

    /**
     * Set the cache repository implementation.
     *
     * @param \MacropaySolutions\Kernel\Contracts\Cache\Repository $cache
     * @return $this
     */
    public function setCache(CacheContract $cache)
    {
        $this->cache = $cache;

        return $this;
    }

    /**
     * Set the name of the worker.
     *
     * @param string $name
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Register a callback to be executed to pick jobs.
     *
     * @param string $workerName
     * @param callable $callback
     * @return void
     */
    public static function popUsing($workerName, $callback)
    {
        if (is_null($callback)) {
            unset(static::$popCallbacks[$workerName]);
        } else {
            static::$popCallbacks[$workerName] = $callback;
        }
    }

    /**
     * Get the queue manager instance.
     *
     * @return \MacropaySolutions\Kernel\Contracts\Queue\Factory
     */
    public function getManager()
    {
        return $this->manager;
    }

    /**
     * Set the queue manager instance.
     *
     * @param \MacropaySolutions\Kernel\Contracts\Queue\Factory $manager
     * @return void
     */
    public function setManager(QueueManager $manager)
    {
        $this->manager = $manager;
    }

    protected function registerPhpErrorCallback(
        WorkerOptions $options,
        string $queue,
        string $connectionName,
    ): void {
        $config = \app('config');
        $output = $config->get(
            'logging.channels.' . $config->get('logging.default') . '.send_bg_commands_output_to',
            (DIRECTORY_SEPARATOR === '\\') ? 'NUL' : '/dev/null'
        );
        $emergencyMemory = \str_repeat('x', 1048576 * $config->get('queue.emergency_memory', 1));
        \register_shutdown_function(function () use (
            &$emergencyMemory,
            $options,
            $queue,
            $connectionName,
            $output
        ): void {
            $emergencyMemory = null;

            if (
                $this->snapshotForFailJobInBgDueToPhpError === []
                || (null === $error = \error_get_last())
                || E_ERROR !== ($error['type'] ?? '')
            ) {
                return;
            }

            try {
                $key = \array_key_first($this->snapshotForFailJobInBgDueToPhpError);
                /** @var Job $job */
                $job = $this->snapshotForFailJobInBgDueToPhpError[$key];
                $this->cache->put(
                    $key,
                    \method_exists($job, 'getCacheForFailingJobOnPhpError') ?
                        $job->getCacheForFailingJobOnPhpError() :
                        [
                            'rawBody' => $job->getRawBody(),
                            'jobId' => $job->getJobId(),
                        ],
                    120
                );
            } catch (\Throwable $e) {
                try {
                    $this->exceptions->report($e);
                } catch (\Throwable) {
                }

                return;
            }

            $error = \json_encode(
                $error,
                JSON_PARTIAL_OUTPUT_ON_ERROR
            );

            if (false === $error) {
                $error = '{}';
            }

            $arguments = [
                'queue:fail-job',
                (string)$key,
                \base64_encode($error),
                $connectionName,
                '--name=' . $options->name,
                '--queue=' . $queue,
            ];

            foreach ($arguments as $k => $argument) {
                if (\str_contains($argument, "\0")) {
                    return;
                }

                $arguments[$k] = ProcessUtils::escapeArgument($argument);
            }

            unset($argument, $k);

            $command = Application::phpBinary() . ' ' . Application::runBinary() . ' ' . \implode(' ', $arguments);

            unset($arguments);

            if (\str_contains((string)$output, "\0")) {
                return;
            }

            $output = ProcessUtils::escapeArgument((string)$output);

            if (windows_os()) {
                \pclose(\popen(
                    'start "" /b '
                    . $command
                    . ' >> '
                    . $output
                    . ' 2>&1',
                    'r'
                ));

                return;
            }

            \pclose(\popen(
                $command
                . ' >> '
                . $output
                . ' 2>&1 &',
                'r'
            ));
        });
    }

    /**
     * @throws \Throwable
     */
    protected function snapshotJobInCaseOfPhpError(Job $job): void
    {
        $this->snapshotForFailJobInBgDueToPhpError = ['php_error_fail_job_' . \hash(
            'sha256',
            $job->getQueue() . $job->getConnectionName() . $job->getName() . ($jobId = $job->getJobId())
        ) => $job];
    }

    /**
     * @throws Throwable
     */
    protected function resetSnapshotJobInCaseOfPhpError(): void
    {
        try {
            if ([] !== $this->snapshotForFailJobInBgDueToPhpError) {
                $this->cache->forget(\array_key_first($this->snapshotForFailJobInBgDueToPhpError));
            }
        } catch (\Throwable $e) {
            $this->exceptions->report($e);
        } finally {
            $this->snapshotForFailJobInBgDueToPhpError = [];
        }
    }
}
