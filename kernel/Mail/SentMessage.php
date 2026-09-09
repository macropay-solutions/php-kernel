<?php

namespace MacropaySolutions\Kernel\Mail;

use InvalidArgumentException;
use JsonSerializable;
use LogicException;
use MacropaySolutions\Kernel\Support\Traits\ForwardsCalls;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage as SymfonySentMessage;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\RawMessage;

/**
 * @mixin \Symfony\Component\Mailer\SentMessage
 */
class SentMessage implements JsonSerializable
{
    use ForwardsCalls;

    /**
     * The Symfony SentMessage instance.
     *
     * @var \Symfony\Component\Mailer\SentMessage
     */
    protected $sentMessage;

    /**
     * Create a new SentMessage instance.
     */
    public function __construct(SymfonySentMessage|array $sentMessage)
    {
        if (\is_array($sentMessage)) {
            if (
                !isset($sentMessage['raw'], $sentMessage['sender'], $sentMessage['recipients']) ||
                !\is_string($sentMessage['raw']) ||
                (!\is_string($sentMessage['sender']) && !$sentMessage['sender'] instanceof Address) ||
                !\is_array($sentMessage['recipients'])
            ) {
                throw new InvalidArgumentException('A serialized sent message must contain valid raw, sender, and recipients.');
            }

            $recipients = [];

            foreach ($sentMessage['recipients'] as $recipient) {
                if (!\is_string($recipient) && !$recipient instanceof Address) {
                    throw new InvalidArgumentException('Recipient must be a string or Address instance.');
                }

                $recipients[] = Address::create($recipient);
            }

            if ([] === $recipients) {
                throw new InvalidArgumentException('A serialized sent message must contain at least one recipient.');
            }

            $sender = Address::create($sentMessage['sender']);
            $rawMessage = new RawMessage($sentMessage['raw']);
            $envelope = new Envelope($sender, $recipients);

            $this->sentMessage = new SymfonySentMessage($rawMessage, $envelope);

            return;
        }

        $this->sentMessage = $sentMessage;
    }

    /**
     * Get the underlying Symfony Email instance.
     *
     * @return \Symfony\Component\Mailer\SentMessage
     */
    public function getSymfonySentMessage()
    {
        return $this->sentMessage;
    }

    /**
     * Dynamically pass missing methods to the Symfony instance.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->forwardCallTo($this->sentMessage, $method, $parameters);
    }

    /**
    * Convert the sent message to a storable JSON array representation.
    */
    public function toArray(): array
    {
        $original = $this->sentMessage->getOriginalMessage();
        $envelope = $this->sentMessage->getEnvelope();

        return [
            'raw' => $original->toString(),
            'sender' => $envelope->getSender()->toString(),
            'recipients' => \array_map(
                fn (Address $a) => $a->toString(),
                $envelope->getRecipients()
            ),
        ];
    }

    /**
     * Specify data which should be serialized to JSON.
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Get the serializable representation of the object.
     *
     * @return array
     */
    public function __serialize()
    {
        throw new LogicException('SentMessage instances cannot be serialized using native PHP serialize().');
    }

    /**
     * Marshal the object from its serialized data.
     *
     * @param array $data
     * @return void
     */
    public function __unserialize(array $data)
    {
        throw new LogicException('SentMessage instances cannot be unserialized using native PHP unserialize().');
    }
}
