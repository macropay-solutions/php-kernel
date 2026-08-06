<?php

namespace MacropaySolutions\Kernel\Queue;

use Aws\Sqs\SqsClient;
use MacropaySolutions\Kernel\Contracts\Queue\ClearableQueue;
use MacropaySolutions\Kernel\Contracts\Queue\Queue as QueueContract;
use MacropaySolutions\Kernel\Queue\Jobs\SqsJob;
use MacropaySolutions\Kernel\Support\Str;

class SqsQueue extends Queue implements QueueContract, ClearableQueue
{
    /**
     * The Amazon SQS instance.
     *
     * @var \Aws\Sqs\SqsClient
     */
    protected $sqs;

    /**
     * The name of the default queue.
     *
     * @var string
     */
    protected $default;

    /**
     * The queue URL prefix.
     *
     * @var string
     */
    protected $prefix;

    /**
     * The queue name suffix.
     *
     * @var string
     */
    protected $suffix;

    /**
     * Create a new Amazon SQS queue instance.
     *
     * @param \Aws\Sqs\SqsClient $sqs
     * @param string $default
     * @param string $prefix
     * @param string $suffix
     * @param bool $dispatchAfterCommit
     * @return void
     */
    public function __construct(
        SqsClient $sqs,
        $default,
        $prefix = '',
        $suffix = '',
        $dispatchAfterCommit = false
    ) {
        $this->sqs = $sqs;
        $this->prefix = $prefix;
        $this->default = $default;
        $this->suffix = $suffix;
        $this->dispatchAfterCommit = $dispatchAfterCommit;
    }

    /**
     * Get the size of the queue.
     *
     * @param string|null $queue
     * @return int
     */
    public function size($queue = null)
    {
        $response = $this->sqs->getQueueAttributes([
            'QueueUrl' => $this->getQueue($queue),
            'AttributeNames' => ['ApproximateNumberOfMessages'],
        ]);

        $attributes = $response->get('Attributes');

        return (int)$attributes['ApproximateNumberOfMessages'];
    }

    /**
     * Push a new job onto the queue.
     *
     * @param string $job
     * @param mixed $data
     * @param string|null $queue
     * @return mixed
     */
    public function push($job, $data = '', $queue = null)
    {
        return $this->enqueueUsing(
            $job,
            $this->createPayload($job, $queue ?: $this->default, $data),
            $queue,
            null,
            function ($payload, $queue) use ($job) {
                return $this->pushRaw($payload, $queue, $this->getQueueableOptions($job, $queue));
            }
        );
    }

    /**
     * Push a raw payload onto the queue.
     *
     * @param string $payload
     * @param string|null $queue
     * @param array $options
     * @return mixed
     */
    public function pushRaw($payload, $queue = null, array $options = [])
    {
        return $this->sqs->sendMessage([
            'QueueUrl' => $this->getQueue($queue),
            'MessageBody' => $payload,
        ] + $options)->get('MessageId');
    }

    /**
     * Push a new job onto the queue after (n) seconds.
     *
     * @param \DateTimeInterface|\DateInterval|int $delay
     * @param string $job
     * @param mixed $data
     * @param string|null $queue
     * @return mixed
     */
    public function later($delay, $job, $data = '', $queue = null)
    {
        return $this->enqueueUsing(
            $job,
            $this->createPayload($job, $queue ?: $this->default, $data),
            $queue,
            $delay,
            function ($payload, $queue, $delay) use ($job) {
                return $this->pushRaw($payload, $queue, $this->getQueueableOptions($job, $queue, $delay));
            }
        );
    }

    /**
     * Get the queueable options from the job.
     * @param \DateTimeInterface|\DateInterval|int|null $delay
     * @return array{DelaySeconds?: int, MessageGroupId?: string, MessageDeduplicationId?: string}
     */
    public function getQueueableOptions(mixed $job, ?string $queue, mixed $delay = null): array
    {
        // Make sure we have a queue name to properly determine if it's a FIFO queue...
        $queue ??= (string)$this->default;

        $callableClass = null;
        $callableArgs = [];

        if (\is_array($job) && isset($job[0], $job[1])) {
            $callableClass = $job[0];
            $callableArgs = $job[2] ?? [];
        }

        if ($job instanceof CallQueuedCallable) {
            $callableClass = $job->storableCallable[0] ?? null;
            $callableArgs = $job->storableCallable[2] ?? [];
        }

        $options = [];

        if (!\str_ends_with($queue, '.fifo')) {
            // DelaySeconds cannot be used with FIFO queues. AWS will return an error...
            if (isset($delay)) {
                $options['DelaySeconds'] = $this->secondsUntil($delay);
            }

            $group = $this->resolveMessageGroupId($job, $callableClass, $callableArgs);

            if ((string)$group !== '') {
                $options['MessageGroupId'] = $group;
            }

            return \array_filter($options);
        }

        // The message group ID is required for FIFO queues and is optional for standard queues.
        $options['MessageGroupId'] = $this->resolveMessageGroupId($job, $callableClass, $callableArgs) ?: $queue;

        // The message deduplication ID is only valid for FIFO queues.
        $options['MessageDeduplicationId'] = $this->resolveDeduplicationId($job, $callableClass, $callableArgs);

        return \array_filter($options);
    }

    /**
     * Resolve the Message Group ID for the job.
     */
    protected function resolveMessageGroupId(mixed $job, ?string $callableClass, array $callableArgs): ?string
    {
        if (\is_object($job)) {
            if (isset($job->messageGroup) && (string)$job->messageGroup !== '') {
                return (string)$job->messageGroup;
            }

            if (\method_exists($job, 'messageGroup')) {
                return (string)$job->messageGroup();
            }
        }

        if ((string)$callableClass !== '' && \is_callable([$callableClass, 'messageGroup'])) {
            return (string)$callableClass::messageGroup($callableArgs);
        }

        return null;
    }

    /**
     * Resolve the Message Deduplication ID for the job.
     */
    protected function resolveDeduplicationId(mixed $job, ?string $callableClass, array $callableArgs): string
    {
        if (\is_object($job) && \method_exists($job, 'deduplicationId')) {
            return (string)$job->deduplicationId();
        }

        if ((string)$callableClass !== '' && \is_callable([$callableClass, 'deduplicationId'])) {
            return (string)$callableClass::deduplicationId($callableArgs);
        }

        return Str::orderedUuid()->toString();
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
            if (isset($job->delay)) {
                $this->later($job->delay, $job, $data, $queue);
            } else {
                $this->push($job, $data, $queue);
            }
        }
    }

    /**
     * Pop the next job off of the queue.
     *
     * @param string|null $queue
     * @return \MacropaySolutions\Kernel\Contracts\Queue\Job|null
     */
    public function pop($queue = null)
    {
        $response = $this->sqs->receiveMessage([
            'QueueUrl' => $queue = $this->getQueue($queue),
            'AttributeNames' => ['ApproximateReceiveCount'],
        ]);

        if (!is_null($response['Messages']) && count($response['Messages']) > 0) {
            return new SqsJob(
                $this->container,
                $this->sqs,
                $response['Messages'][0],
                $this->connectionName,
                $queue
            );
        }

        return null;
    }

    /**
     * Return the job that must be failed
     */
    public function getJobToBeFailedFromCachedData(array $data, ?string $queue = null): \MacropaySolutions\Kernel\Contracts\Queue\Job
    {
        return new SqsJob(
            $this->container,
            $this->sqs,
            $data['job'],
            $this->connectionName,
            $this->getQueue($queue)
        );
    }

    /**
     * Delete all the jobs from the queue.
     *
     * @param string $queue
     * @return int
     */
    public function clear($queue)
    {
        return tap($this->size($queue), function () use ($queue) {
            $this->sqs->purgeQueue([
                'QueueUrl' => $this->getQueue($queue),
            ]);
        });
    }

    /**
     * Get the queue or return the default.
     *
     * @param string|null $queue
     * @return string
     */
    public function getQueue($queue)
    {
        $queue = $queue ?: $this->default;

        return filter_var($queue, FILTER_VALIDATE_URL) === false
            ? $this->suffixQueue($queue, $this->suffix)
            : $queue;
    }

    /**
     * Add the given suffix to the given queue name.
     *
     * @param string $queue
     * @param string $suffix
     * @return string
     */
    protected function suffixQueue($queue, $suffix = '')
    {
        if (str_ends_with($queue, '.fifo')) {
            $queue = Str::beforeLast($queue, '.fifo');

            return rtrim($this->prefix, '/') . '/' . Str::finish($queue, $suffix) . '.fifo';
        }

        return rtrim($this->prefix, '/') . '/' . Str::finish($queue, $this->suffix);
    }

    /**
     * Get the underlying SQS instance.
     *
     * @return \Aws\Sqs\SqsClient
     */
    public function getSqs()
    {
        return $this->sqs;
    }
}
