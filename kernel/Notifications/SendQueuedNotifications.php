<?php

namespace MacropaySolutions\Kernel\Notifications;

use MacropaySolutions\Kernel\Bus\Queueable;
use MacropaySolutions\Kernel\Contracts\Queue\Job;
use MacropaySolutions\Kernel\Contracts\Queue\ShouldBeEncrypted;
use MacropaySolutions\Kernel\Contracts\Queue\ShouldQueue;
use MacropaySolutions\Kernel\Contracts\Queue\ShouldQueueAfterCommit;
use MacropaySolutions\Kernel\Contracts\Queue\StorableCallable;
use MacropaySolutions\Kernel\Database\Obvious\Collection as ObviousCollection;
use MacropaySolutions\Kernel\Database\Obvious\Model;
use MacropaySolutions\Kernel\Queue\CallQueuedCallable;
use MacropaySolutions\Kernel\Queue\InteractsWithQueue;
use MacropaySolutions\Kernel\Queue\SerializesModels;
use MacropaySolutions\Kernel\Queue\SerializesModelsHelper;
use MacropaySolutions\Kernel\Support\Collection;

class SendQueuedNotifications implements ShouldQueue, StorableCallable
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The notifiable entities that should receive the notification.
     *
     * @var \MacropaySolutions\Kernel\Support\Collection|null
     */
    public $notifiables;

    /**
     * The notification to be sent.
     *
     * @var \MacropaySolutions\Kernel\Notifications\Notification|null
     */
    public $notification;

    /**
     * All the channels to send the notification to.
     *
     * @var array|null
     */
    public $channels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int|null
     */
    public $tries;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int|null
     */
    public $timeout;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     *
     * @var int|null
     */
    public $maxExceptions;

    /**
     * Indicates if the job should be encrypted.
     *
     * @var bool
     */
    public $shouldBeEncrypted = false;

    /**
     * Create a new job instance.
     *
     * @param \MacropaySolutions\Kernel\Notifications\Notifiable|\MacropaySolutions\Kernel\Support\Collection $notifiables
     * @param \MacropaySolutions\Kernel\Notifications\Notification $notification
     * @param array|null $channels
     * @return void
     */
    public function __construct($notifiables, $notification, ?array $channels = null)
    {
        $this->channels = $channels;
        $this->notification = $notification;
        $this->notifiables = $this->wrapNotifiables($notifiables);
        $this->tries = $notification->tries ?? null;
        $this->timeout = $notification->timeout ?? null;
        $this->maxExceptions = $notification->maxExceptions ?? null;

        $this->afterCommit = $notification instanceof ShouldQueueAfterCommit
            ? true
            : ($notification->afterCommit ?? null);

        $this->shouldBeEncrypted = $notification instanceof ShouldBeEncrypted;
    }

    /**
     * Wrap the notifiable(s) in a collection.
     *
     * @param \MacropaySolutions\Kernel\Notifications\Notifiable|\MacropaySolutions\Kernel\Support\Collection $notifiables
     * @return \MacropaySolutions\Kernel\Support\Collection
     */
    protected function wrapNotifiables($notifiables)
    {
        if ($notifiables instanceof Collection) {
            return $notifiables;
        }

        if ($notifiables instanceof Model) {
            return ObviousCollection::wrap($notifiables);
        }

        return Collection::wrap($notifiables);
    }

    /**
     * The Converter: Extracts pure primitive data. Zero objects allowed.
     */
    public function toStorableCallable(): CallQueuedCallable
    {
        $notifiablesData = [];

        $notifiables = $this->notifiables;


        if (!$notifiables instanceof \MacropaySolutions\Kernel\Support\Collection && !\is_array($notifiables)) {
            $notifiables = [$notifiables];
        }

        foreach ($notifiables as $notifiable) {
            if ($notifiable instanceof Model) {
                $notifiablesData[] = ['type' => 'model', 'class' => \get_class($notifiable), 'id' => $notifiable->getKey()];

                continue;
            }

            if ($notifiable instanceof AnonymousNotifiable) {
                $notifiablesData[] = ['type' => 'anonymous', 'routes' => $notifiable->routes];
            }
        }

        $notificationProps = isset($this->notification) ? \get_object_vars($this->notification) : [];

        $helper = new SerializesModelsHelper();

        foreach ($notificationProps as $key => $value) {
            $notificationProps[$key] = $helper->serializePropertyValue($value);
        }

        $payload = [
            'notifiables' => $notifiablesData,
            'notificationClass' => isset($this->notification) ? \get_class($this->notification) : '',
            'notificationProps' => $notificationProps,
            'channels' => $this->channels,
        ];

        $callable = CallQueuedCallable::createFrom($this->notification, [self::class, 'executeStorable', $payload]);

        $callable->onFailure([self::class, 'executeFailedStorable', [
            'notifiables'       => $payload['notifiables'],
            'notificationClass' => $payload['notificationClass'],
            'notificationProps' => $payload['notificationProps'],
            'channels'          => $payload['channels'],
        ]]);

        $callable->connection = $this->connection;
        $callable->queue = $this->queue;
        $callable->delay = $this->delay;
        $callable->afterCommit = $this->afterCommit;
        $callable->tries = $this->tries;
        $callable->timeout = $this->timeout;
        $callable->maxExceptions = $this->maxExceptions;

        return $callable;
    }

    /**
     * The Worker Execution Bridge. Rebuilds state dynamically.
     */
    public static function executeStorable(
        array $notifiables,
        string $notificationClass,
        array $notificationProps,
        ?array $channels,
        Job $job
    ): void {
        $restoredNotifiables = new Collection();

        foreach ($notifiables as $n) {
            if ($n['type'] === 'model') {
                $restoredNotifiables->push((new $n['class'])->newQueryForRestoration([$n['id']])->useWritePdo()->firstOrFail());

                continue;
            }

            if ($n['type'] === 'anonymous') {
                $anon = new AnonymousNotifiable();
                $anon->routes = $n['routes'];
                $restoredNotifiables->push($anon);
            }
        }

        $notification = \app($notificationClass);
        $helper = new SerializesModelsHelper();

        foreach ($notificationProps as $key => $value) {
            $notification->$key = $helper->restorePropertyValue($value);
        }

        $wrapper = new self($restoredNotifiables, $notification, $channels);

        if (\method_exists($wrapper, 'setJob')) {
            $wrapper->setJob($job);
        }
        
        // Forward the job instance to the inner notification if it interacts with the queue
        if (\method_exists($notification, 'setJob')) {
            $notification->setJob($job);
        }

        \app()->call([$wrapper, 'handle']);
    }

    public static function executeFailedStorable(
        array $notifiables,
        string $notificationClass,
        array $notificationProps,
        ?array $channels,
        \Throwable $e
    ): void
    {
        $restoredNotifiables = new Collection();

        foreach ($notifiables as $n) {
            if ($n['type'] === 'model') {
                $restoredNotifiables->push((new $n['class'])
                    ->newQueryForRestoration([$n['id']])->useWritePdo()->firstOrFail());

                continue;
            }

            if ($n['type'] === 'anonymous') {
                $anon = new AnonymousNotifiable();
                $anon->routes = $n['routes'];
                $restoredNotifiables->push($anon);
            }
        }

        $notification = \app($notificationClass);
        $helper = new SerializesModelsHelper();

        foreach ($notificationProps as $key => $value) {
            $notification->$key = $helper->restorePropertyValue($value);
        }

        (new self($restoredNotifiables, $notification, $channels))->failed($e);
    }

    /**
     * Legacy handle method for synchronous dispatches.
     */
    public function handle(ChannelManager $manager)
    {
        $manager->sendNow($this->notifiables, $this->notification, $this->channels);
    }

    /**
     * Get the display name for the queued job.
     */
    public function displayName()
    {
        return \is_object($this->notification) ? \get_class($this->notification) : static::class;
    }

    /**
     * Call the failed method on the notification instance.
     *
     * @param \Throwable $e
     * @return void
     */
    public function failed($e)
    {
        if (\is_object($this->notification) && \method_exists($this->notification, 'failed')) {
            $this->notification->failed($e);
        }
    }

    /**
     * Get the number of seconds before a released notification will be available.
     */
    public function backoff()
    {
        if (
            !\is_object($this->notification)
            || (
                !\method_exists($this->notification, 'backoff')
                && !isset($this->notification->backoff)
            )
        ) {
            return;
        }

        return $this->notification->backoff ?? $this->notification->backoff();
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil()
    {
        if (!\is_object($this->notification) || (!\method_exists($this->notification, 'retryUntil') && !isset($this->notification->retryUntil))) {
            return;
        }

        return $this->notification->retryUntil ?? $this->notification->retryUntil();
    }

    /**
     * Prepare the instance for cloning.
     */
    public function __clone()
    {
        if (\is_object($this->notifiables)) {
            $this->notifiables = clone $this->notifiables;
        }

        if (\is_object($this->notification)) {
            $this->notification = clone $this->notification;
        }
    }
}
