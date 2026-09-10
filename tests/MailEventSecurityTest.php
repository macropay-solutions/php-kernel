<?php

namespace Tests\Unit\Mail;

use MacropaySolutions\Kernel\Mail\Events\MessageSending;
use MacropaySolutions\Kernel\Mail\Events\MessageSent;
use MacropaySolutions\Kernel\Mail\Mailable;
use MacropaySolutions\Kernel\Mail\SendQueuedMailable;
use MacropaySolutions\Kernel\Mail\SentMessage;
use MacropaySolutions\Kernel\Contracts\Queue\ShouldQueue;
use MacropaySolutions\KernelDev\Foundation\Testing\TestCase;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage as SymfonySentMessage;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

class SampleQueuedMailable extends Mailable implements ShouldQueue
{
    public ?int $orderId = 123;
}

class MailEventSecurityTest extends TestCase
{
    /**
     * Create the application instance for testing.
     */
    public function createApplication()
    {
        $app = require __DIR__ . '/../../bootstrap/app.php';
        $app->make(\MacropaySolutions\Kernel\Contracts\Console\Kernel::class)->bootstrap();

        return $app;
    }

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

        \json_encode($sentMessage, JSON_THROW_ON_ERROR);
    }

    public function test_message_sent_event_blocks_native_serialization(): void
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

        \json_encode($event, JSON_THROW_ON_ERROR);
    }

    public function test_message_sending_event_blocks_native_serialization(): void
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

        \json_encode($event, JSON_THROW_ON_ERROR);
    }

    public function test_mailable_implementing_should_queue_can_be_queued(): void
    {
        $mailable = new SampleQueuedMailable();

        $queueFake = new \MacropaySolutions\KernelDev\Support\Testing\Fakes\QueueFake($this->app, []);
        $this->app->instance('queue', $queueFake);

        \app('mailer')->to('user@example.com')->queue($mailable);

        $queueFake->assertPushed(SendQueuedMailable::class);
    }

    public function test_should_queue_listener_on_message_sent_event_throws_logic_exception(): void
    {
        $queuedListener = new class implements ShouldQueue {
            public function handle(MessageSent $event): void {}
        };

        /** @var \MacropaySolutions\Kernel\Contracts\Events\Dispatcher $dispatcher */
        $dispatcher = $this->app->make(\MacropaySolutions\Kernel\Contracts\Events\Dispatcher::class);
        $dispatcher->listen(MessageSent::class, $queuedListener);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('MessageSent events cannot be queued. They do not support JSON serialization.');

        $dispatcher->dispatch(new MessageSent($this->createMockSentMessage()));
    }

    public function test_should_queue_listener_on_message_sending_event_throws_logic_exception(): void
    {
        $queuedListener = new class implements ShouldQueue {
            public function handle(MessageSending $event): void {}
        };

        /** @var \MacropaySolutions\Kernel\Contracts\Events\Dispatcher $dispatcher */
        $dispatcher = $this->app->make(\MacropaySolutions\Kernel\Contracts\Events\Dispatcher::class);
        $dispatcher->listen(MessageSending::class, $queuedListener);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('MessageSending events cannot be queued. They do not support JSON serialization.');

        $dispatcher->dispatch(new MessageSending(new Email()));
    }

    public function test_should_queue_job_holding_sent_message_throws_logic_exception(): void
    {
        $queuedJob = new class($this->createMockSentMessage()) implements ShouldQueue {
            public function __construct(
                public SentMessage $sentMessage
            ) {}
        };

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('SentMessage instances cannot be jsonSerialized.');

        \json_encode($queuedJob, JSON_THROW_ON_ERROR);
    }
}