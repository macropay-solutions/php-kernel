<?php

namespace MacropaySolutions\Kernel\Notifications\Events;

use MacropaySolutions\Kernel\Bus\Queueable;
use MacropaySolutions\Kernel\Queue\SerializesModels;

class NotificationSending
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
     * Create a new event instance.
     *
     * @param mixed $notifiable
     * @param \MacropaySolutions\Kernel\Notifications\Notification $notification
     * @param string $channel
     * @return void
     */
    public function __construct($notifiable, $notification, $channel)
    {
        $this->channel = $channel;
        $this->notifiable = $notifiable;
        $this->notification = $notification;
    }
}
