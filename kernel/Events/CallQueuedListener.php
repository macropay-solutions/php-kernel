<?php

namespace MacropaySolutions\Kernel\Events;

use MacropaySolutions\Kernel\Bus\Queueable;
use MacropaySolutions\Kernel\Container\Container;
use MacropaySolutions\Kernel\Contracts\Queue\Job;
use MacropaySolutions\Kernel\Contracts\Queue\ShouldQueue;
use MacropaySolutions\Kernel\Contracts\Queue\StorableCallable;
use MacropaySolutions\Kernel\Queue\CallQueuedCallable;
use MacropaySolutions\Kernel\Queue\SerializesModelsHelper;
use MacropaySolutions\Kernel\Queue\InteractsWithQueue;

class CallQueuedListener implements ShouldQueue, StorableCallable
{
    use InteractsWithQueue;
    use Queueable;

    /**
     * The listener class name.
     */
    public string $class;

    /**
     * The listener method.
     */
    public string $method;

    /**
     * The data to be passed to the listener or event constructor.
     */
    public array $data;

    /**
     * The event class name to reconstruct on the worker.
     */
    public ?string $eventClass;

    /**
     * The number of times the job may be attempted.
     */
    public ?int $tries = null;

    /**
     * The maximum number of exceptions allowed, regardless of attempts.
     */
    public ?int $maxExceptions = null;

    /**
     * The number of seconds to wait before retrying a job that encountered an uncaught exception.
     */
    public ?int $backoff = null;

    public ?int $retryUntil = null;

    /**
     * The number of seconds the job can run before timing out.
     */
    public ?int $timeout = null;

    /**
     * Indicates if the job should fail if the timeout is exceeded.
     */
    public bool $failOnTimeout = false;

    /**
     * Indicates if the job should be encrypted.
     */
    public bool $shouldBeEncrypted = false;

    /**
     * Create a new job instance.
     *
     * @param string $class
     * @param string $method
     * @param array $data
     * @param string|null $eventClass
     */
    public function __construct(string $class, string $method, array $data, ?string $eventClass = null)
    {
        $this->class = $class;
        $this->method = $method;
        $this->data = $data;
        $this->eventClass = $eventClass;
    }

    /**
     * Handle the queued job.
     *
     * @param Container $container
     * @return void
     */
    public function handle(Container $container): void
    {
        $handler = $this->setJobInstanceIfNecessary(
            $this->job,
            $container->make($this->class)
        );

        $handler->{$this->method}(...$this->reconstructEvent());
    }

    /**
     * Reinstantiate the event object in worker memory if an eventClass is present.
     */
    public function reconstructEvent(): array
    {
        if ('' !== (string)$this->eventClass && \class_exists($this->eventClass)) {
            if (isset($this->data[0]) && $this->data[0] instanceof $this->eventClass) {
                return $this->data;
            }

            if (isset($this->data[1][0]) && $this->data[1][0] instanceof $this->eventClass) {
                return $this->data;
            }
        }

        $data = $this->data;
        $helper = new SerializesModelsHelper();

        if ('' !== (string)$this->eventClass && \class_exists($this->eventClass)) {
            // Shape 1: Direct Event Object
            if (isset($data[0]) && \is_array($data[0])) {
                foreach ($data[0] as $key => $value) {
                    $data[0][$key] = $helper->restorePropertyValue($value);
                }

                $data[0] = \app($this->eventClass, $data[0]);

                return $data;
            }

            // Shape 2: Named Event
            if (isset($data[1][0]) && \is_array($data[1][0]) && \is_string($data[0])) {
                foreach ($data[1][0] as $key => $value) {
                    $data[1][0][$key] = $helper->restorePropertyValue($value);
                }

                $data[1][0] = \app($this->eventClass, $data[1][0]);

                return $data;
            }
        }

        return \is_array($data) ? $data : [$data];
    }

    /**
     * The Converter: Passes pure primitive arrays directly over the wire.
     */
    public function toStorableCallable(): CallQueuedCallable
    {
        $storableData = $this->getStorableData();

        $payload = [
            'class' => $this->class,
            'method' => $this->method,
            'data' => $storableData,
            'eventClass' => $this->eventClass,
        ];

        $callable = CallQueuedCallable::createFrom($this->class, [self::class, 'executeStorable', $payload]);
        $callable->onFailure([self::class, 'executeFailedStorable', $payload]);

        $callable->connection = $this->connection;
        $callable->queue = $this->queue;
        $callable->timeout = $this->timeout;
        $callable->tries = $this->tries;
        $callable->maxExceptions = $this->maxExceptions;
        $callable->backoff = $this->backoff;
        $callable->retryUntil = $this->retryUntil;
        $callable->failOnTimeout = $this->failOnTimeout;
        $callable->shouldBeEncrypted = $this->shouldBeEncrypted;
        
        $callable->messageGroup =
            \method_exists($this->class, 'messageGroup') ? (string)$this->class::messageGroup($storableData) : null;
        $callable->deduplicationId = \method_exists($this->class, 'deduplicationId') ?
            (string)$this->class::deduplicationId($storableData) :
            null;

        return $callable;
    }

    /**
     * Execute the storable callable on the queue worker.
     */
    public static function executeStorable(
        string $class,
        string $method,
        array $data,
        ?string $eventClass,
        Job $job
    ): void {
        $wrapper = new self($class, $method, $data, $eventClass);

        if (\method_exists($wrapper, 'setJob')) {
            $wrapper->setJob($job);
        }

        \app()->call([$wrapper, 'handle']);
    }

    /**
     * Execute the failed storable callable on the queue worker.
     */
    public static function executeFailedStorable(
        string $class,
        string $method,
        array $data,
        ?string $eventClass,
        \Throwable $e
    ): void {
        (new self($class, $method, $data, $eventClass))->failed($e);
    }

    /**
     * Set the job instance of the given class if necessary.
     *
     * @param Job $job
     * @param object $instance
     * @return object
     */
    protected function setJobInstanceIfNecessary(Job $job, object $instance): object
    {
        if (\in_array(InteractsWithQueue::class, \class_uses_recursive($instance), true)) {
            $instance->setJob($job);
        }

        return $instance;
    }

    /**
     * Call the failed method on the listener instance.
     */
    public function failed(\Throwable $e): void
    {
        $handler = Container::getInstance()->make($this->class);

        if (\method_exists($handler, 'failed')) {
            $handler->failed(...\array_merge(\array_values($this->reconstructEvent()), [$e]));
        }
    }

    /**
     * Get the display name for the queued job.
     */
    public function displayName(): string
    {
        return $this->class;
    }

    /**
     * Prepare the instance for cloning.
     *
     * @return void
     */
    public function __clone(): void
    {
        $this->data = array_map(function ($data) {
            return is_object($data) ? clone $data : $data;
        }, $this->data);
    }

    protected function getStorableData(): array
    {
        if ('' === (string)$this->eventClass) {
            return $this->data;
        }

        $storableData = $this->data;
        $helper = new SerializesModelsHelper();

        // Shape 1: Direct Event Object [$event]
        if (isset($storableData[0]) && $storableData[0] instanceof $this->eventClass) {
            $storableData[0] = \get_object_vars($storableData[0]);

            foreach ($storableData[0] as $key => $value) {
                $storableData[0][$key] = $helper->serializePropertyValue($value);
            }

            return $storableData;
        }

        // Shape 2: Named Event [$eventName, [$event]]
        if (isset($storableData[1][0]) && $storableData[1][0] instanceof $this->eventClass) {
            $storableData[1][0] = \get_object_vars($storableData[1][0]);

            foreach ($storableData[1][0] as $key => $value) {
                $storableData[1][0][$key] = $helper->serializePropertyValue($value);
            }

            return $storableData;
        }

        return $storableData;
    }
}
