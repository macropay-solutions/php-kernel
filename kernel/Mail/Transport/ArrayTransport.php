<?php

namespace MacropaySolutions\Kernel\Mail\Transport;

use MacropaySolutions\Kernel\Support\Collection;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;

class ArrayTransport implements TransportInterface
{
    /**
     * The collection of Symfony Messages.
     *
     * @var \MacropaySolutions\Kernel\Support\Collection
     */
    protected $messages;

    /**
     * Create a new array transport instance.
     *
     * @return void
     */
    public function __construct()
    {
//        $this->messages = new Collection;
        $this->messages = \di(Collection::class);
    }

    /**
     * {@inheritdoc}
     */
    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        return $this->messages[] = new SentMessage($message, $envelope ?? Envelope::create($message));
    }

    /**
     * Retrieve the collection of messages.
     *
     * @return \MacropaySolutions\Kernel\Support\Collection
     */
    public function messages()
    {
        return $this->messages;
    }

    /**
     * Clear all the messages from the local collection.
     *
     * @return \MacropaySolutions\Kernel\Support\Collection
     */
    public function flush()
    {
//        return $this->messages = new Collection;
        return $this->messages = \di(Collection::class);
    }

    /**
     * Get the string representation of the transport.
     *
     * @return string
     */
    public function __toString(): string
    {
        return 'array';
    }
}
