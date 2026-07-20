<?php

namespace MacropaySolutions\Kernel\Queue;

use Exception;
use MacropaySolutions\Kernel\Bus\Batchable;
use MacropaySolutions\Kernel\Bus\BatchRepository;
use MacropaySolutions\Kernel\Bus\UniqueLock;
use MacropaySolutions\Kernel\Contracts\Bus\Dispatcher;
use MacropaySolutions\Kernel\Contracts\Cache\Factory as CacheFactory;
use MacropaySolutions\Kernel\Contracts\Cache\Repository as Cache;
use MacropaySolutions\Kernel\Contracts\Container\Container;
use MacropaySolutions\Kernel\Contracts\Encryption\Encrypter;
use MacropaySolutions\Kernel\Contracts\Queue\Job;
use MacropaySolutions\Kernel\Contracts\Queue\ShouldBeUnique;
use MacropaySolutions\Kernel\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use MacropaySolutions\Kernel\Database\Obvious\ModelNotFoundException;
use MacropaySolutions\Kernel\Pipeline\Pipeline;
use ReflectionClass;
use RuntimeException;

class CallQueuedHandler
{
    /**
     * The bus dispatcher implementation.
     *
     * @var \MacropaySolutions\Kernel\Contracts\Bus\Dispatcher
     */
    protected $dispatcher;

    /**
     * The container instance.
     *
     * @var \MacropaySolutions\Kernel\Contracts\Container\Container
     */
    protected $container;

    protected static array $classUsesRecursiveMap = [];

    /**
     * Create a new handler instance.
     *
     * @param \MacropaySolutions\Kernel\Contracts\Bus\Dispatcher $dispatcher
     * @param \MacropaySolutions\Kernel\Contracts\Container\Container $container
     * @return void
     */
    public function __construct(Dispatcher $dispatcher, Container $container)
    {
        $this->container = $container;
        $this->dispatcher = $dispatcher;
    }

    /**
     * Handle the queued job.
     *
     * @param \MacropaySolutions\Kernel\Contracts\Queue\Job $job
     * @param array $data
     * @return void
     */
    public function call(Job $job, array $data)
    {
        $payload = $job->payload();
        $lockAlreadyReleased = false;

        if (
            ($payload['uniqueUntilProcessing'] ?? false) === true
            && '' !== (string)($payload['uniqueJobKey'] ?? '')
        ) {
            $this->container->make(CacheFactory::class)
                ->store($payload['uniqueJobCacheStore'] ?? null)
                ->lock($payload['uniqueJobKey'])
                ->forceRelease();

            $lockAlreadyReleased = true;
        }

        try {
            $command = $this->setJobInstanceIfNecessary(
                $job,
                $this->getCommand($data)
            );
        } catch (ModelNotFoundException $e) {
            $this->handleModelNotFound($job, $e);

            return;
        }

        $this->dispatchThroughMiddleware($job, $command, $lockAlreadyReleased);

        if (!$job->isReleased() && !$command instanceof ShouldBeUniqueUntilProcessing) {
            $this->ensureUniqueJobLockIsReleased($command, $job);
        }

        if (!$job->hasFailed() && !$job->isReleased()) {
            $this->ensureNextJobInChainIsDispatched($command);
            $this->ensureSuccessfulBatchJobIsRecorded($command);
        }

        if (!$job->isDeletedOrReleased()) {
            $job->delete();
        }
    }

    /**
     * Get the command from the given payload.
     *
     * @param array $data
     * @return mixed
     *
     * @throws \RuntimeException
     */
    protected function getCommand(array $data)
    {
        $command = $data['command'];

        if (
            !\str_starts_with($command, '{') &&
            !\str_starts_with($command, '[') &&
            $this->container->bound(Encrypter::class)
        ) {
            $command = $this->container[Encrypter::class]->decryptString($command);
        }

        $unserialized = \json_decode($command, true, flags: JSON_THROW_ON_ERROR);

        if (\is_array($unserialized) && isset($unserialized['storableCallable'])) {
            $job = CallQueuedCallable::create($unserialized['storableCallable']);

            foreach ($unserialized as $key => $value) {
                if ($key === 'storableCallable') {
                    continue;
                }

                if ($key === 'delay' && \is_string($value)) {
                    if (\str_starts_with($value, 'datetime:')) {
                        $job->{$key} = \MacropaySolutions\Kernel\Support\Carbon::parse(\substr($value, 9));

                        continue;
                    }

                    if (\str_starts_with($value, 'dateinterval:')) {
                        $job->{$key} = new \DateInterval(\substr($value, 13));

                        continue;
                    }
                }

                $job->{$key} = $value;
            }

            return $job;
        }

        throw new RuntimeException('Unable to extract job payload.');
    }

    /**
     * Dispatch the given job / command through its specified middleware.
     *
     * @param \MacropaySolutions\Kernel\Contracts\Queue\Job $job
     * @param mixed $command
     * @param bool $lockAlreadyReleased
     * @return mixed
     * @throws Exception
     */
    protected function dispatchThroughMiddleware(Job $job, $command, bool $lockReleased = false)
    {
        if ($command instanceof \__PHP_Incomplete_Class) {
            throw new Exception('Job is incomplete class: ' . json_encode($command));
        }

        return (new Pipeline($this->container))->send($command)
            ->through(
                array_merge(
                    method_exists($command, 'middleware') ? $command->middleware() : [],
                    $command->middleware ?? []
                )
            )
            ->finally(function (mixed $command) use (&$lockReleased, $job): void {
                if (
                    !$lockReleased
                    && $command instanceof ShouldBeUniqueUntilProcessing
                    && ($command->job ?? null) instanceof Job
                    && !$command->job->isReleased()
                ) {
                    $this->ensureUniqueJobLockIsReleased($command, $job);
                }
            })
            ->then(function (mixed $command) use ($job, &$lockReleased): mixed {
                if ($command instanceof ShouldBeUniqueUntilProcessing) {
                    if (!$lockReleased) {
                        $this->ensureUniqueJobLockIsReleased($command, $job);
                        $lockReleased = true;
                    }
                }

                return $this->dispatcher->dispatchNow(
                    $command,
                    $this->resolveHandler($job, $command)
                );
            });
    }

    /**
     * Resolve the handler for the given command.
     *
     * @param \MacropaySolutions\Kernel\Contracts\Queue\Job $job
     * @param mixed $command
     * @return mixed
     */
    protected function resolveHandler($job, $command)
    {
        $handler = $this->dispatcher->getCommandHandler($command) ?: null;

        if ($handler) {
            $this->setJobInstanceIfNecessary($job, $handler);
        }

        return $handler;
    }

    /**
     * Set the job instance of the given class if necessary.
     *
     * @param \MacropaySolutions\Kernel\Contracts\Queue\Job $job
     * @param mixed $instance
     * @return mixed
     */
    protected function setJobInstanceIfNecessary(Job $job, $instance)
    {
        if (
            \in_array(
                InteractsWithQueue::class,
                static::$classUsesRecursiveMap[
                    \is_object($instance) ? $instance::class : $instance
                ] ??= \class_uses_recursive($instance),
                true
            )
        ) {
            $instance->setJob($job);
        }

        return $instance;
    }

    /**
     * Ensure the next job in the chain is dispatched if applicable.
     *
     * @param mixed $command
     * @return void
     */
    protected function ensureNextJobInChainIsDispatched($command)
    {
        if (method_exists($command, 'dispatchNextJobInChain')) {
            $command->dispatchNextJobInChain();
        }
    }

    /**
     * Ensure the batch is notified of the successful job completion.
     *
     * @param mixed $command
     * @return void
     */
    protected function ensureSuccessfulBatchJobIsRecorded($command)
    {
        $uses = static::$classUsesRecursiveMap[
            \is_object($command) ? $command::class : $command
        ] ??= \class_uses_recursive($command);

        if (
            \in_array(Batchable::class, $uses, true)
            && \in_array(InteractsWithQueue::class, $uses, true)
        ) {
            $command->batch()?->recordSuccessfulJob($command->job->uuid());
        }
    }

    /**
     * Ensure the lock for a unique job is released.
     *
     * @param mixed $command
     * @param \MacropaySolutions\Kernel\Contracts\Queue\Job|null $job
     * @return void
     */
    protected function ensureUniqueJobLockIsReleased($command, ?Job $job = null)
    {
        if ($command instanceof ShouldBeUnique) {
            (new UniqueLock($this->container->make(Cache::class)))->release($command);

            return;
        }

        if ($job !== null) {
            $payload = $job->payload();

            if (
                ($payload['uniqueUntilProcessing'] ?? false) === false
                && '' !== (string)($payload['uniqueJobKey'] ?? '')
            ) {
                $store = $payload['uniqueJobCacheStore'] ?? null;
                $this->container->make(CacheFactory::class)
                    ->store($store)
                    ->lock($payload['uniqueJobKey'])
                    ->forceRelease();
            }
        }
    }

    /**
     * Handle a model not found exception.
     *
     * @param \MacropaySolutions\Kernel\Contracts\Queue\Job $job
     * @param \Throwable $e
     * @return void
     */
    protected function handleModelNotFound(Job $job, $e)
    {
        $class = $job->resolveName();

        try {
            $shouldDelete = (new ReflectionClass($class))
                ->getDefaultProperties()['deleteWhenMissingModels'] ?? false;
        } catch (Exception) {
            $shouldDelete = false;
        }

        $jobPayload = $job->payload();

        /** ensureUniqueJobLockIsReleasedViaJobPayload */
        $this->ensureUniqueJobLockIsReleasedViaJobPayload($job);

        if ($shouldDelete) {
            /** ensureSuccessfulBatchJobIsRecordedForMissingModel */
            if (
                \is_string($batchId = $jobPayload['data']['batchId'] ?? null)
                && $batchId !== ''
                && \is_string($jobUUID = $job->uuid())
                && $jobUUID !== ''
                && $this->container->bound(BatchRepository::class)
                && \in_array(
                    Batchable::class,
                    static::$classUsesRecursiveMap[$class] ??= \class_uses_recursive($class),
                    true
                )
            ) {
                $this->container->make(BatchRepository::class)->find($batchId)?->recordSuccessfulJob($jobUUID);
            }

            $job->delete();

            return;
        }

        $job->fail($e);
    }

    /**
     * Ensure the lock for a unique job is released via job payload.
     *
     * This is required when we can't unserialize the job due to missing models.
     */
    protected function ensureUniqueJobLockIsReleasedViaJobPayload(Job $job): void
    {
        $jobPayload = $job->payload();
        $store = $jobPayload['uniqueJobCacheStore'] ?? '';
        $key = $jobPayload['uniqueJobKey'] ?? '';

        if ('' === $store || '' === $key) {
            return;
        }

        $this->container->make(CacheFactory::class)
            ->store($store)
            ->lock($key)
            ->forceRelease();
    }

    /**
     * Call the failed method on the job instance.
     *
     * The exception that caused the failure will be passed.
     *
     * @param array $data
     * @param \Throwable|null $e
     * @param string $uuid
     * @param \MacropaySolutions\Kernel\Contracts\Queue\Job|null $job
     */
    public function failed(array $data, $e, string $uuid, ?Job $job = null): void
    {
        $command = $this->getCommand($data);

        if ($job instanceof Job) {
            $command = $this->setJobInstanceIfNecessary($job, $command);
        }

        if (!$command instanceof ShouldBeUniqueUntilProcessing) {
            $this->ensureUniqueJobLockIsReleased($command, $job);
        }

        if ($command instanceof \__PHP_Incomplete_Class) {
            return;
        }

        $this->ensureFailedBatchJobIsRecorded($uuid, $command, $e);
        $this->ensureChainCatchCallbacksAreInvoked($uuid, $command, $e);

        if (method_exists($command, 'failed')) {
            $command->failed($e);
        }
    }

    /**
     * Ensure the batch is notified of the failed job.
     *
     * @param string $uuid
     * @param mixed $command
     * @param \Throwable $e
     * @return void
     */
    protected function ensureFailedBatchJobIsRecorded(string $uuid, $command, $e)
    {
        if (
            \in_array(
                Batchable::class,
                static::$classUsesRecursiveMap[
                    \is_object($command) ? $command::class : $command
                ] ??= \class_uses_recursive($command),
                true
            )
        ) {
            $command->batch()?->recordFailedJob($uuid, $e);
        }
    }

    /**
     * Ensure the chained job catch callbacks are invoked.
     *
     * @param string $uuid
     * @param mixed $command
     * @param \Throwable $e
     * @return void
     */
    protected function ensureChainCatchCallbacksAreInvoked(string $uuid, $command, $e)
    {
        if (method_exists($command, 'invokeChainCatchCallbacks')) {
            $command->invokeChainCatchCallbacks($e);
        }
    }
}
