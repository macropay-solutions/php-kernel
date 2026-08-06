<?php

namespace MacropaySolutions\Kernel\Broadcasting;

use MacropaySolutions\Kernel\Bus\Queueable;
use MacropaySolutions\Kernel\Contracts\Broadcasting\Factory as BroadcastingFactory;
use MacropaySolutions\Kernel\Contracts\Queue\ShouldQueue;
use MacropaySolutions\Kernel\Contracts\Queue\StorableCallable;
use MacropaySolutions\Kernel\Contracts\Support\Arrayable;
use MacropaySolutions\Kernel\Queue\CallQueuedCallable;
use MacropaySolutions\Kernel\Support\Arr;

class BroadcastEvent implements ShouldQueue, StorableCallable
{
    use Queueable;

    /**
     * The event instance.
     *
     * @var mixed
     */
    public $event;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout;

    /**
     * The number of seconds to wait before retrying the job when encountering an uncaught exception.
     *
     * @var int
     */
    public $backoff;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     *
     * @var int
     */
    public $maxExceptions;

    /**
     * Create a new job handler instance.
     *
     * @param mixed $event
     * @return void
     */
    public function __construct($event)
    {
        $this->event = $event;
        $this->tries = property_exists($event, 'tries') ? $event->tries : null;
        $this->timeout = property_exists($event, 'timeout') ? $event->timeout : null;
        $this->backoff = property_exists($event, 'backoff') ? $event->backoff : null;
        $this->afterCommit = property_exists($event, 'afterCommit') ? $event->afterCommit : null;
        $this->maxExceptions = property_exists($event, 'maxExceptions') ? $event->maxExceptions : null;
    }

    /**
     * Handle the queued job.
     *
     * @param \MacropaySolutions\Kernel\Contracts\Broadcasting\Factory $manager
     * @return void
     */
    public function handle(BroadcastingFactory $manager)
    {
        $name = method_exists($this->event, 'broadcastAs')
            ? $this->event->broadcastAs() : get_class($this->event);

        $channels = Arr::wrap($this->event->broadcastOn());

        if ([] === $channels) {
            return;
        }

        $connections = method_exists($this->event, 'broadcastConnections')
            ? $this->event->broadcastConnections()
            : [null];

        $payload = $this->getPayloadFromEvent($this->event);

        foreach ($connections as $connection) {
            $manager->connection($connection)->broadcast(
                $channels,
                $name,
                $payload
            );
        }
    }

    /**
     * The Converter: Extracts primitive data on the web server. Zero objects go to the queue.
     */
    public function toStorableCallable(): CallQueuedCallable
    {
        $name = \method_exists($this->event, 'broadcastAs') ? $this->event->broadcastAs() : \get_class($this->event);
        $channels = \MacropaySolutions\Kernel\Support\Arr::wrap($this->event->broadcastOn());
        
        $formattedChannels = [];

        foreach ($channels as $channel) {
            $formattedChannels[] = (string) $channel;
        }

        $connections = \method_exists($this->event, 'broadcastConnections') ? $this->event->broadcastConnections() : [null];
        $payload = $this->getPayloadFromEvent($this->event);

        $callable = CallQueuedCallable::createFrom($this->event, [
            self::class,
            'executeStorable',
            [
                'name'        => $name,
                'channels'    => $formattedChannels,
                'payload'     => $payload,
                'connections' => $connections,
            ]
        ]);

        $callable->connection = $this->connection;
        $callable->queue = $this->queue;
        $callable->timeout = $this->timeout;
        $callable->tries = $this->tries;
        $callable->backoff = $this->backoff;
        $callable->maxExceptions = $this->maxExceptions;
        
        $callable->messageGroup = $this->event->messageGroup ??
            (\method_exists($this->event, 'messageGroup') ? (string)$this->event->messageGroup() : null);
        $callable->deduplicationId =
            \method_exists($this->event, 'deduplicationId') ? (string)$this->event->deduplicationId() : null;

        return $callable;
    }

    public static function executeStorable(
        string $name,
        array $channels,
        array $payload,
        array $connections,
        BroadcastingFactory $manager
    ): void {
        if ([] === $channels) {
            return;
        }

        foreach ($connections as $connection) {
            $manager->connection($connection)->broadcast($channels, $name, $payload);
        }
    }

    /**
     * Get the payload for the given event.
     *
     * @param mixed $event
     * @return array
     */
    protected function getPayloadFromEvent($event)
    {
        if (
            \method_exists($event, 'broadcastWith') &&
            \is_array($payload = $event->broadcastWith()) &&
            !\array_is_list($payload)
        ) {
            return \array_merge($payload, ['socket' => data_get($event, 'socket')]);
        }

        throw new \RuntimeException('Strict Broadcast Mode: Event [' . \get_class($event) .
            '] must implement a `broadcastWith()` method returning an array. Public property reflection is forbidden.');
    }

    /**
     * Format the given value for a property.
     *
     * @param mixed $value
     * @return mixed
     */
    protected function formatProperty($value)
    {
        if ($value instanceof Arrayable) {
            return $value->toArray();
        }

        return $value;
    }

    /**
     * Get the display name for the queued job.
     *
     * @return string
     */
    public function displayName()
    {
        return get_class($this->event);
    }

    /**
     * Prepare the instance for cloning.
     *
     * @return void
     */
    public function __clone()
    {
        $this->event = clone $this->event;
    }
}
