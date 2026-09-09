<?php

namespace MacropaySolutions\Kernel\Mail\Events;

use Exception;
use LogicException;
use MacropaySolutions\Kernel\Mail\SentMessage;

/**
 * @property \Symfony\Component\Mime\Email $message
 */
class MessageSent
{
    /**
     * The message that was sent.
     *
     * @var \MacropaySolutions\Kernel\Mail\SentMessage
     */
    public $sent;

    /**
     * The message data.
     *
     * @var array
     */
    public $data;

    /**
     * Create a new event instance.
     */
    public function __construct(SentMessage|array $sent, array $data = [])
    {
        $this->sent = \is_array($sent) ? new SentMessage($sent) : $sent;
        $this->data = $data;
    }

    /**
     * Prevent native PHP serialization.
     */
    public function __serialize(): array
    {
        throw new LogicException('MessageSent events cannot be serialized using native PHP serialize().');
    }

    /**
     * Prevent native PHP unserialization.
     */
    public function __unserialize(array $data): void
    {
        throw new LogicException('MessageSent events cannot be unserialized using native PHP unserialize().');
    }

    /**
     * Dynamically get the original message.
     *
     * @param string $key
     * @return mixed
     *
     * @throws \Exception
     */
    public function __get($key)
    {
        if ($key === 'message') {
            return $this->sent->getOriginalMessage();
        }

        throw new Exception('Unable to access undefined property on ' . __CLASS__ . ': ' . $key);
    }
}
