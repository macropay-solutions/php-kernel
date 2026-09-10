<?php

namespace MacropaySolutions\Kernel\Mail\Events;

use Symfony\Component\Mime\Email;

class MessageSending implements \JsonSerializable
{
    /**
     * Create a new event instance.
     */
    public function __construct(
        public Email $message,
        public array $data = []
    ) {
    }

    /**
     * Prevent JSON serialization for queue transport.
     */
    public function jsonSerialize(): array
    {
        throw new \LogicException('MessageSending events cannot be queued. They do not support JSON serialization.');
    }

    /**
     * Prevent native PHP serialization.
     */
    public function __serialize(): array
    {
        throw new \LogicException('MessageSending events cannot be serialized using native PHP serialize().');
    }

    /**
     * Prevent native PHP unserialization.
     */
    public function __unserialize(array $data): void
    {
        throw new \LogicException('MessageSending events cannot be unserialized using native PHP unserialize().');
    }
}
