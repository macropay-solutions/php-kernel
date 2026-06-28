<?php

namespace MacropaySolutions\Kernel\Notifications\Events;

use MacropaySolutions\Kernel\Bus\Queueable;
use MacropaySolutions\Kernel\Queue\SerializesModels;

class NotificationFailed
{
    use Queueable;
    use SerializesModels;

    /**
     * The notifiable entity who received the notification.
     *
     * @var mixed
     */
    public $notifiable;

    /**
     * The notification instance.
     *
     * @var \MacropaySolutions\Kernel\Notifications\Notification
     */
    public $notification;

    /**
     * The channel name.
     *
     * @var string
     */
    public $channel;

    /**
     * The data needed to process this failure.
     *
     * @var array
     */
    public $data = [];

    /**
     * Create a new event instance.
     *
     * @param mixed $notifiable
     * @param \MacropaySolutions\Kernel\Notifications\Notification $notification
     * @param string $channel
     * @param array $data
     * @return void
     */
    public function __construct($notifiable, $notification, $channel, $data = [])
    {
        $this->data = $data;
        $this->channel = $channel;
        $this->notifiable = $notifiable;
        $this->notification = $notification;
    }
}
