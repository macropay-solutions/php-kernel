<?php

namespace MacropaySolutions\Kernel\Mail;

use MacropaySolutions\Kernel\Macroable\Traits\ExplicitForwardable;
use MacropaySolutions\Kernel\Support\Traits\ForwardsCalls;
use Symfony\Component\Mailer\SentMessage as SymfonySentMessage;

/**
 * @mixin \Symfony\Component\Mailer\SentMessage
 */
class SentMessage implements \JsonSerializable
{
    use ExplicitForwardable;

    /**
     * Create a new SentMessage instance.
     */
    public function __construct(
        protected SymfonySentMessage $sentMessage
    ) {
    }

    /**
     * Get the underlying Symfony Email instance.
     *
     * @return \Symfony\Component\Mailer\SentMessage
     */
    public function getBaseSentMessage()
    {
        return $this->sentMessage;
    }

    /**
     * Dynamically pass missing methods to the Symfony instance.
     */
    public function to(): \Symfony\Component\Mailer\SentMessage
    {
        return $this->getBaseSentMessage();
    }

    /**
     * Prevent JSON serialization for queue transport.
     */
    public function jsonSerialize(): array
    {
        throw new \LogicException('SentMessage instances cannot be jsonSerialized.');
    }

    /**
     * Prevent native PHP serialization.
     */
    public function __serialize(): array
    {
        throw new \LogicException('SentMessage instances cannot be serialized using native PHP serialize().');
    }

    /**
     * Prevent native PHP unserialization.
     */
    public function __unserialize(array $data): void
    {
        throw new \LogicException('SentMessage instances cannot be unserialized using native PHP unserialize().');
    }
}
