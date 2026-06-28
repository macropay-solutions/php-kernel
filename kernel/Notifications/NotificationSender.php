<?php

namespace MacropaySolutions\Kernel\Notifications;

use MacropaySolutions\Kernel\Contracts\Queue\ShouldQueue;
use MacropaySolutions\Kernel\Contracts\Translation\HasLocalePreference;
use MacropaySolutions\Kernel\Database\Obvious\Collection as ModelCollection;
use MacropaySolutions\Kernel\Database\Obvious\Model;
use MacropaySolutions\Kernel\Notifications\Events\NotificationSending;
use MacropaySolutions\Kernel\Notifications\Events\NotificationSent;
use MacropaySolutions\Kernel\Support\Collection;
use MacropaySolutions\Kernel\Support\Str;
use MacropaySolutions\Kernel\Support\Traits\Localizable;

class NotificationSender
{
    use Localizable;

    /**
     * The notification manager instance.
     *
     * @var \MacropaySolutions\Kernel\Notifications\ChannelManager
     */
    protected $manager;

    /**
     * The Bus dispatcher instance.
     *
     * @var \MacropaySolutions\Kernel\Contracts\Bus\Dispatcher
     */
    protected $bus;

    /**
     * The event dispatcher.
     *
     * @var \MacropaySolutions\Kernel\Contracts\Events\Dispatcher
     */
    protected $events;

    /**
     * The locale to be used when sending notifications.
     *
     * @var string|null
     */
    protected $locale;

    /**
     * Create a new notification sender instance.
     *
     * @param \MacropaySolutions\Kernel\Notifications\ChannelManager $manager
     * @param \MacropaySolutions\Kernel\Contracts\Bus\Dispatcher $bus
     * @param \MacropaySolutions\Kernel\Contracts\Events\Dispatcher $events
     * @param string|null $locale
     * @return void
     */
    public function __construct($manager, $bus, $events, $locale = null)
    {
        $this->bus = $bus;
        $this->events = $events;
        $this->locale = $locale;
        $this->manager = $manager;
    }

    /**
     * Send the given notification to the given notifiable entities.
     *
     * @param \MacropaySolutions\Kernel\Support\Collection|array|mixed $notifiables
     * @param mixed $notification
     * @return void
     */
    public function send($notifiables, $notification)
    {
        $notifiables = $this->formatNotifiables($notifiables);

        if ($notification instanceof ShouldQueue) {
            $this->queueNotification($notifiables, $notification);

            return;
        }

        $this->sendNow($notifiables, $notification);
    }

    /**
     * Send the given notification immediately.
     *
     * @param \MacropaySolutions\Kernel\Support\Collection|array|mixed $notifiables
     * @param mixed $notification
     * @param array|null $channels
     * @return void
     */
    public function sendNow($notifiables, $notification, ?array $channels = null)
    {
        $notifiables = $this->formatNotifiables($notifiables);

        $original = clone $notification;

        foreach ($notifiables as $notifiable) {
            if (empty($viaChannels = $channels ?: $notification->via($notifiable))) {
                continue;
            }

            $this->withLocale(
                $this->preferredLocale($notifiable, $notification),
                function () use ($viaChannels, $notifiable, $original) {
                    $notificationId = Str::uuid()->toString();

                    foreach ((array)$viaChannels as $channel) {
                        if (!($notifiable instanceof AnonymousNotifiable && $channel === 'database')) {
                            $this->sendToNotifiable($notifiable, $notificationId, clone $original, $channel);
                        }
                    }
                }
            );
        }
    }

    /**
     * Get the notifiable's preferred locale for the notification.
     *
     * @param mixed $notifiable
     * @param mixed $notification
     * @return string|null
     */
    protected function preferredLocale($notifiable, $notification)
    {
        return $notification->locale ?? $this->locale ?? value(function () use ($notifiable) {
            if ($notifiable instanceof HasLocalePreference) {
                return $notifiable->preferredLocale();
            }
        });
    }

    /**
     * Send the given notification to the given notifiable via a channel.
     *
     * @param mixed $notifiable
     * @param string $id
     * @param mixed $notification
     * @param string $channel
     * @return void
     */
    protected function sendToNotifiable($notifiable, $id, $notification, $channel)
    {
        if (!$notification->id) {
            $notification->id = $id;
        }

        if (!$this->shouldSendNotification($notifiable, $notification, $channel)) {
            return;
        }

        $response = $this->manager->driver($channel)->send($notifiable, $notification);

        $this->events->dispatch(
            new NotificationSent($notifiable, $notification, $channel, $response)
        );
    }

    /**
     * Determines if the notification can be sent.
     *
     * @param mixed $notifiable
     * @param mixed $notification
     * @param string $channel
     * @return bool
     */
    protected function shouldSendNotification($notifiable, $notification, $channel)
    {
        if (
            method_exists($notification, 'shouldSend') &&
            $notification->shouldSend($notifiable, $channel) === false
        ) {
            return false;
        }

        return $this->events->until(new NotificationSending($notifiable, $notification, $channel)) !== false;
    }

    /**
     * Queue the given notification instances.
     *
     * @param mixed $notifiables
     * @param \MacropaySolutions\Kernel\Notifications\Notification $notification
     * @return void
     */
    protected function queueNotification($notifiables, $notification)
    {
        $notifiables = $this->formatNotifiables($notifiables);

        $original = clone $notification;

        foreach ($notifiables as $notifiable) {
            $notificationId = Str::uuid()->toString();

            foreach ((array)$original->via($notifiable) as $channel) {
                $notification = clone $original;

                if (!$notification->id) {
                    $notification->id = $notificationId;
                }

                if (!is_null($this->locale)) {
                    $notification->locale = $this->locale;
                }

                $connection = $notification->connection;

                if (method_exists($notification, 'viaConnections')) {
                    $connection = $notification->viaConnections()[$channel] ?? null;
                }

                $queue = $notification->queue;

                if (method_exists($notification, 'viaQueues')) {
                    $queue = $notification->viaQueues()[$channel] ?? null;
                }

                $delay = $notification->delay;

                if (method_exists($notification, 'withDelay')) {
                    $delay = $notification->withDelay($notifiable, $channel) ?? null;
                }

                $middleware = $notification->middleware ?? [];

                if (method_exists($notification, 'middleware')) {
                    $middleware = array_merge(
                        $notification->middleware($notifiable, $channel),
                        $middleware
                    );
                }

                $this->bus->dispatch(
                    (new SendQueuedNotifications($notifiable, $notification, [$channel]))
                        ->onConnection($connection)
                        ->onQueue($queue)
                        ->delay(is_array($delay) ? ($delay[$channel] ?? null) : $delay)
                        ->through($middleware)
                );
            }
        }
    }

    /**
     * Format the notifiables into a Collection / array if necessary.
     *
     * @param mixed $notifiables
     * @return \MacropaySolutions\Kernel\Database\Obvious\Collection|array
     */
    protected function formatNotifiables($notifiables)
    {
        if (!$notifiables instanceof Collection && !is_array($notifiables)) {
            return $notifiables instanceof Model
                ? new ModelCollection([$notifiables]) : [$notifiables];
        }

        return $notifiables;
    }
}
