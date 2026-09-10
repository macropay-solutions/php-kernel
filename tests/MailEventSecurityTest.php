<?php

use MacropaySolutions\Kernel\Mail\Events\MessageSending;
use MacropaySolutions\Kernel\Mail\Events\MessageSent;
use MacropaySolutions\Kernel\Mail\SentMessage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\SentMessage as SymfonySentMessage;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\Mime\Address;

class MailEventSecurityTest extends TestCase
{
    private function createMockSentMessage(): SentMessage
    {
        $symfonySentMessage = new SymfonySentMessage(
            new RawMessage('Subject: Test'),
            new Envelope(new Address('sender@example.com'), [new Address('recipient@example.com')])
        );

        return new SentMessage($symfonySentMessage);
    }

    public function test_sent_message_blocks_native_serialization(): void
    {
        $sentMessage = $this->createMockSentMessage();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('SentMessage instances cannot be serialized using native PHP serialize().');

        \serialize($sentMessage);
    }

    public function test_sent_message_blocks_json_serialization(): void
    {
        $sentMessage = $this->createMockSentMessage();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('SentMessage instances cannot be jsonSerialized.');

        \json_encode($sentMessage);
    }

    public function test_message_sent_event_blocks_serialization(): void
    {
        $event = new MessageSent($this->createMockSentMessage());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('MessageSent events cannot be serialized using native PHP serialize().');

        \serialize($event);
    }

    public function test_message_sent_event_blocks_json_serialization(): void
    {
        $event = new MessageSent($this->createMockSentMessage());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('MessageSent events cannot be queued. They do not support JSON serialization.');

        \json_encode($event);
    }

    public function test_message_sending_event_blocks_serialization(): void
    {
        $event = new MessageSending(new Email());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('MessageSending events cannot be serialized using native PHP serialize().');

        \serialize($event);
    }

    public function test_message_sending_event_blocks_json_serialization(): void
    {
        $event = new MessageSending(new Email());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('MessageSending events cannot be queued. They do not support JSON serialization.');

        \json_encode($event);
    }
}