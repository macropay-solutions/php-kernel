<?php

namespace MacropaySolutions\Kernel\Mail\Events;

use MacropaySolutions\Kernel\Mail\SentMessage;

/**
 * @property \Symfony\Component\Mime\Email $message
 */
class MessageSent implements \JsonSerializable
{
    /**
     * Create a new event instance.
     */
    public function __construct(
        public SentMessage $sent,
        public array $data = []
    ) {
    }

    /**
     * Prevent JSON serialization for queue transport.
     */
    public function jsonSerialize(): array
    {
        throw new \LogicException('MessageSent events cannot be queued. They do not support JSON serialization.');
    }

    /**
     * Prevent native PHP serialization.
     */
    public function __serialize(): array
    {
        throw new \LogicException('MessageSent events cannot be serialized using native PHP serialize().');
    }

    /**
     * Prevent native PHP unserialization.
     */
    public function __unserialize(array $data): void
    {
        throw new \LogicException('MessageSent events cannot be unserialized using native PHP unserialize().');
    }

    /**
     * Dynamically get the original message.
     *
     * @throws \Exception
     */
    public function __get(string $key): mixed
    {
        if ($key === 'message') {
            return $this->sent->getOriginalMessage();
        }

        throw new \Exception('Unable to access undefined property on ' . __CLASS__ . ': ' . $key);
    }
}
