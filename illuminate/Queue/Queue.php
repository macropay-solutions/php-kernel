<?php

namespace Illuminate\Queue;

use Closure;
use DateTimeInterface;
use Illuminate\Bus\UniqueLock;
use Illuminate\Container\Container;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Queue\Events\JobQueueing;
use Illuminate\Support\Arr;
use Illuminate\Support\InteractsWithTime;
use Illuminate\Support\Str;

abstract class Queue
{
    use InteractsWithTime;

    /**
     * The IoC container instance.
     *
     * @var \Illuminate\Container\Container
     */
    protected $container;

    /**
     * The connection name for the queue.
     *
     * @var string
     */
    protected $connectionName;

    /**
     * Indicates that jobs should be dispatched after all database transactions have committed.
     *
     * @var bool
     */
    protected $dispatchAfterCommit;

    /**
     * The create payload callbacks.
     *
     * @var callable[]
     */
    protected static $createPayloadCallbacks = [];

    /**
     * Push a new job onto the queue.
     *
     * @param string $queue
     * @param string $job
     * @param mixed $data
     * @return mixed
     */
    public function pushOn($queue, $job, $data = '')
    {
        return $this->push($job, $data, $queue);
    }

    /**
     * Push a new job onto a specific queue after (n) seconds.
     *
     * @param string $queue
     * @param \DateTimeInterface|\DateInterval|int $delay
     * @param string $job
     * @param mixed $data
     * @return mixed
     */
    public function laterOn($queue, $delay, $job, $data = '')
    {
        return $this->later($delay, $job, $data, $queue);
    }

    /**
     * Push an array of jobs onto the queue.
     *
     * @param array $jobs
     * @param mixed $data
     * @param string|null $queue
     * @return void
     */
    public function bulk($jobs, $data = '', $queue = null)
    {
        foreach ((array)$jobs as $job) {
            $this->push($job, $data, $queue);
        }
    }

    /**
     * Create a payload string from the given job and data.
     *
     * @param \Closure|string|object $job
     * @param string $queue
     * @param mixed $data
     * @return string
     *
     * @throws \Illuminate\Queue\InvalidPayloadException
     */
    protected function createPayload($job, $queue, $data = '')
    {
        if ($job instanceof Closure) {
            throw new \RuntimeException('Closure serialization forbidden.');
        }

        if (\is_array($job)) {
            $job = CallQueuedCallable::create($job);
        }

        if ($job instanceof ShouldBeUnique) {
            self::createPayloadUsing(fn(): array => [
                'uniqueJobKey' => UniqueLock::getKey($job),
                'uniqueJobCacheStore' => UniqueLock::getUniqueJobCacheStore($job)
            ]);
        }

        $payload = json_encode($value = $this->createPayloadArray($job, $queue, $data), \JSON_UNESCAPED_UNICODE);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidPayloadException(
                'Unable to JSON encode payload. Error (' . json_last_error() . '): ' . json_last_error_msg(),
                $value
            );
        }

        return $payload;
    }

    /**
     * Create a payload array from the given job and data.
     *
     * @param string|object $job
     * @param string $queue
     * @param mixed $data
     * @return array
     */
    protected function createPayloadArray($job, $queue, $data = '')
    {
        return is_object($job)
            ? $this->createObjectPayload($job, $queue)
            : $this->createStringPayload($job, $queue, $data);
    }

    /**
     * Create a payload for an object-based queue handler.
     *
     * @param object $job
     * @param string $queue
     * @return array
     */
    protected function createObjectPayload($job, $queue)
    {
        $payload = $this->withCreatePayloadHooks($queue, [
            'uuid' => (string)Str::uuid(),
            'displayName' => $this->getDisplayName($job),
            /**
             * @see \Illuminate\Queue\CallQueuedHandler::call()
             */
            'job' => 'Illuminate\Queue\CallQueuedHandler@call',
            'maxTries' => $this->getJobTries($job),
            'maxExceptions' => $job->maxExceptions ?? null,
            'failOnTimeout' => $job->failOnTimeout ?? false,
            'backoff' => $this->getJobBackoff($job),
            'timeout' => $job->timeout ?? null,
            'retryUntil' => $this->getJobExpiration($job),
            'data' => [
                'commandName' => $job,
                'command' => $job,
                'batchId' => $job->batchId ?? null,
            ],
        ]);

        if ($job instanceof CallQueuedCallable) {
            $metadata = \get_object_vars($job);

            if (isset($metadata['delay'])) {
                $metadata['delay'] = match (true) {
                    $metadata['delay'] instanceof \DateTimeInterface =>
                        'datetime:' . $metadata['delay']->format('c'), // ISO 8601 string
                    $metadata['delay'] instanceof \DateInterval =>
                        'dateinterval:' . $metadata['delay']->format('P%yY%mM%dDT%hH%iM%sS'),
                    default => $metadata['delay'],
                };
            }

            static::ensureNoObjects($metadata);

            return $this->getMergedObjectJobPayload($payload, $job, \serialize($metadata));
        }

        return $this->getMergedObjectJobPayload($payload, $job, \serialize($job));
    }

    /**
     * Get the display name for the given job.
     *
     * @param object $job
     * @return string
     */
    protected function getDisplayName($job)
    {
        return method_exists($job, 'displayName')
            ? $job->displayName() : get_class($job);
    }

    /**
     * Get the maximum number of attempts for an object-based queue handler.
     *
     * @param mixed $job
     * @return ?int
     */
    public function getJobTries($job)
    {
        if (isset($job->tries)) {
            return (int)$job->tries;
        }

        if (method_exists($job, 'tries')) {
            return (int)$job->tries();
        }

        return null;
    }

    /**
     * Get the backoff for an object-based queue handler.
     *
     * @param mixed $job
     * @return mixed
     */
    public function getJobBackoff($job)
    {
        if (!method_exists($job, 'backoff') && !isset($job->backoff)) {
            return;
        }

        if (is_null($backoff = $job->backoff ?? $job->backoff())) {
            return;
        }

        return collect(Arr::wrap($backoff))
            ->map(function ($backoff) {
                return $backoff instanceof DateTimeInterface
                    ? $this->secondsUntil($backoff) : $backoff;
            })->implode(',');
    }

    /**
     * Get the expiration timestamp for an object-based queue handler.
     *
     * @param mixed $job
     * @return mixed
     */
    public function getJobExpiration($job)
    {
        if (!method_exists($job, 'retryUntil') && !isset($job->retryUntil)) {
            return;
        }

        $expiration = $job->retryUntil ?? $job->retryUntil();

        return $expiration instanceof DateTimeInterface
            ? $expiration->getTimestamp() : $expiration;
    }

    /**
     * Determine if the job should be encrypted.
     *
     * @param object $job
     * @return bool
     */
    protected function jobShouldBeEncrypted($job)
    {
        if ($job instanceof ShouldBeEncrypted) {
            return true;
        }

        if ($job instanceof CallQueuedCallable) {
            return \is_subclass_of($job->storableCallable[0], ShouldBeEncrypted::class);
        }

        return $job->shouldBeEncrypted ?? false;
    }

    /**
     * Create a typical, string based queue payload array.
     *
     * @param string $job
     * @param string $queue
     * @param mixed $data
     * @return array
     */
    protected function createStringPayload($job, $queue, $data)
    {
        return $this->withCreatePayloadHooks($queue, [
            'uuid' => (string)Str::uuid(),
            'displayName' => is_string($job) ? explode('@', $job)[0] : null,
            'job' => $job,
            'maxTries' => null,
            'maxExceptions' => null,
            'failOnTimeout' => false,
            'backoff' => null,
            'timeout' => null,
            'data' => $data,
        ]);
    }

    /**
     * Register a callback to be executed when creating job payloads.
     *
     * @param callable|null $callback
     * @return void
     */
    public static function createPayloadUsing($callback)
    {
        if (is_null($callback)) {
            static::$createPayloadCallbacks = [];
        } else {
            static::$createPayloadCallbacks[] = $callback;
        }
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function storableCallable(mixed $callable): array
    {
        if (!\is_array($callable) || !\array_is_list($callable) || !\in_array(\count($callable), [2, 3], true)) {
            throw new \InvalidArgumentException(
                'Objects and Closures are not storable. Use a [Class, method] array .'
            );
        }

        $class = $callable[0];
        $method = $callable[1];

        if (!\is_string($class) || !\is_string($method)) {
            throw new \InvalidArgumentException(
                'Storable validation failed: Array callbacks must use a class and method strings.'
            );
        }

        if (\class_exists($class) && \in_array($method, \get_class_methods($class) ?? [], true)) {
            $callable[2] = (array)($callable[2] ?? []);

            if ($callable[2] !== [] && \array_is_list($callable[2])) {
                throw new \InvalidArgumentException(
                    'Storable validation failed: Array callbacks must not use list array as parameters.'
                );
            }

            static::ensureNoObjects($callable[2]);

            return $callable;
        }

        throw new \InvalidArgumentException(
            'Storable validation failed: Method [' . $method . '] does not exist on class [' . $class . '].'
        );
    }

    /**
     * Recursively ensure no objects are present in the parameter tree.
     * @throws \InvalidArgumentException
     */
    protected static function ensureNoObjects(array $items): void
    {
        foreach ($items as $key => $value) {
            if (\is_object($value)) {
                throw new \InvalidArgumentException(
                    'Security validation failed: Object detected at key [' . $key . ']. ' .
                        'Storable callables only allow primitives (strings, ints, floats, bools, null, or arrays).'
                );
            }

            // Strictly match PHP's exact serialization format for Objects (O:) and Custom Objects (C:)
            // Format: [boundary] O/C : [name_length] : "[class_name]" : [property_count] :
            if (\is_string($value) && \preg_match('/(?:^|;|{)(?:[OC]:\d+:"[^"]+":\d+:|E:\d+:"[^"]+";)/', $value)) {
                throw new \InvalidArgumentException(
                    'Security validation failed: Serialized object signature detected in string at key [' . $key .
                        ']. Storable callables (including their chained jobs and middleware) only allow primitives ' .
                        '(strings, integers, floats, booleans, null, or arrays).'
                );
            }

            if (\is_array($value)) {
                static::ensureNoObjects($value);
            }
        }
    }

    /**
     * Create the given payload using any registered payload hooks.
     *
     * @param string $queue
     * @param array $payload
     * @return array
     */
    protected function withCreatePayloadHooks($queue, array $payload)
    {
        if (!empty(static::$createPayloadCallbacks)) {
            foreach (static::$createPayloadCallbacks as $callback) {
                $payload = array_merge($payload, $callback($this->getConnectionName(), $queue, $payload));
            }
        }

        return $payload;
    }

    /**
     * Enqueue a job using the given callback.
     *
     * @param \Closure|string|object $job
     * @param string $payload
     * @param string $queue
     * @param \DateTimeInterface|\DateInterval|int|null $delay
     * @param callable $callback
     * @return mixed
     */
    protected function enqueueUsing($job, $payload, $queue, $delay, $callback)
    {
        if (
            $this->shouldDispatchAfterCommit($job) &&
            $this->container->bound('db.transactions')
        ) {
            return $this->container->make('db.transactions')->addCallback(
                function () use ($payload, $queue, $delay, $callback, $job) {
                    $this->raiseJobQueueingEvent($job, $payload);

                    return tap($callback($payload, $queue, $delay), function ($jobId) use ($job, $payload) {
                        $this->raiseJobQueuedEvent($jobId, $job, $payload);
                    });
                }
            );
        }

        $this->raiseJobQueueingEvent($job, $payload);

        return tap($callback($payload, $queue, $delay), function ($jobId) use ($job, $payload) {
            $this->raiseJobQueuedEvent($jobId, $job, $payload);
        });
    }

    /**
     * Determine if the job should be dispatched after all database transactions have committed.
     *
     * @param \Closure|string|object $job
     * @return bool
     */
    protected function shouldDispatchAfterCommit($job)
    {
        if (!\is_object($job)) {
            return $this->dispatchAfterCommit ?? false;
        }

        if (!$job instanceof Closure && \is_bool($job->afterCommit ?? null)) {
            return $job->afterCommit;
        }

        return $job instanceof ShouldQueueAfterCommit;
    }

    /**
     * Raise the job queueing event.
     *
     * @param \Closure|string|object $job
     * @param string $payload
     * @return void
     */
    protected function raiseJobQueueingEvent($job, $payload)
    {
        if ($this->container->bound('events')) {
            $this->container['events']->dispatch(new JobQueueing($this->connectionName, $job, $payload));
        }
    }

    /**
     * Raise the job queued event.
     *
     * @param string|int|null $jobId
     * @param \Closure|string|object $job
     * @param string $payload
     * @return void
     */
    protected function raiseJobQueuedEvent($jobId, $job, $payload)
    {
        if ($this->container->bound('events')) {
            $this->container['events']->dispatch(new JobQueued($this->connectionName, $jobId, $job, $payload));
        }
    }

    /**
     * Get the connection name for the queue.
     *
     * @return string
     */
    public function getConnectionName()
    {
        return $this->connectionName;
    }

    /**
     * Set the connection name for the queue.
     *
     * @param string $name
     * @return $this
     */
    public function setConnectionName($name)
    {
        $this->connectionName = $name;

        return $this;
    }

    /**
     * Get the container instance being used by the connection.
     *
     * @return \Illuminate\Container\Container
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Set the IoC container instance.
     *
     * @param \Illuminate\Container\Container $container
     * @return void
     */
    public function setContainer(Container $container)
    {
        $this->container = $container;
    }

    final protected function getMergedObjectJobPayload(array $payload, object $job, string $serialized): array
    {
        $return = \array_merge($payload, [
            'data' => \array_merge($payload['data'], [
                'commandName' => $job::class,
                'command' => $this->jobShouldBeEncrypted($job) && $this->container->bound(Encrypter::class)
                    ? $this->container[Encrypter::class]->encrypt($serialized)
                    : $serialized
            ]),
        ]);

        if (Container::FORBID_SERIALIZED_OBJECTS_IN_QUEUE) {
            static::ensureNoObjects($return);
            static::ensureNoObjects([$serialized]);
        }

        return $return;
    }
}
