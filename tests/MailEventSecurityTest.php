<?php

use MacropaySolutions\Framework\Application;
use MacropaySolutions\Kernel\Container\Container;
use MacropaySolutions\Kernel\Contracts\Events\Dispatcher;
use MacropaySolutions\Kernel\Contracts\Queue\ShouldQueue;
use MacropaySolutions\Kernel\Mail\Events\MessageSending;
use MacropaySolutions\Kernel\Mail\Events\MessageSent;
use MacropaySolutions\Kernel\Mail\Mailable;
use MacropaySolutions\Kernel\Mail\SentMessage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage as SymfonySentMessage;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

class SampleQueuedMailable extends Mailable implements ShouldQueue
{
    public ?int $orderId = 123;
}

class SampleQueuedListener implements ShouldQueue
{
    public function handle($event): void {}
}

class SampleQueuedJob implements ShouldQueue
{
    public function __construct(
        public SentMessage $sentMessage
    ) {}
}

class MailEventSecurityTest extends TestCase
{
    protected Application $app;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app = new Application();
        Container::setInstance($this->app);
    }

    protected function tearDown(): void
    {
        // Restore PHP's native handlers to prevent PHPUnit 11 "Risky" test warnings
        restore_error_handler();
        restore_exception_handler();

        Container::setInstance(null);

        parent::tearDown();
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

        $queueMock = \Mockery::mock(\MacropaySolutions\Kernel\Queue\QueueManager::class);
        $queueMock->shouldReceive('push')
            ->once()
            ->withArgs(function ($job) {
                return $job instanceof \MacropaySolutions\Kernel\Mail\SendQueuedMailable;
            });

        $this->app->instance('queue', $queueMock);

        $this->app->singleton('mailer', function () {
            return new class {
                public function to($address) { return $this; }
                public function queue($mailable) {
                    \app('queue')->push(new \MacropaySolutions\Kernel\Mail\SendQueuedMailable($mailable));
                }
            };
        });

        $this->app->make('mailer')->to('user@example.com')->queue($mailable);
    }

    public function test_should_queue_job_holding_sent_message_throws_logic_exception(): void
    {
        $queuedJob = new SampleQueuedJob($this->createMockSentMessage());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('SentMessage instances cannot be jsonSerialized.');

        \json_encode($queuedJob, JSON_THROW_ON_ERROR);
    }

    public function test_should_queue_listener_on_message_sent_event_throws_logic_exception(): void
    {
        $queueMock = \Mockery::mock(\MacropaySolutions\Kernel\Queue\QueueManager::class);
        $queueMock->shouldIgnoreMissing();

        $serializeSim = function ($job) { \json_encode($job, JSON_THROW_ON_ERROR); };
        $queueMock->shouldReceive('push')->andReturnUsing($serializeSim);
        $queueMock->shouldReceive('pushOn')
            ->andReturnUsing(function ($queue, $job) use ($serializeSim) { $serializeSim($job); });

        $this->app->instance('queue', $queueMock);

        $this->app->singleton('events', function () {
            return (new \MacropaySolutions\Kernel\Events\Dispatcher($this->app))
                ->setQueueResolver(fn () => $this->app->make('queue'));
        });

        /** @var Dispatcher $dispatcher */
        $dispatcher = $this->app->make('events');
        $dispatcher->listen(MessageSent::class, SampleQueuedListener::class);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('MessageSent events cannot be queued. They do not support JSON serialization.');

        $dispatcher->dispatch(new MessageSent($this->createMockSentMessage()));
    }

    public function test_should_queue_listener_on_message_sending_event_throws_logic_exception(): void
    {
        $queueMock = \Mockery::mock(\MacropaySolutions\Kernel\Queue\QueueManager::class);
        $queueMock->shouldIgnoreMissing();

        $serializeSim = function ($job) { \json_encode($job, JSON_THROW_ON_ERROR); };
        $queueMock->shouldReceive('push')->andReturnUsing($serializeSim);
        $queueMock->shouldReceive('pushOn')
            ->andReturnUsing(function ($queue, $job) use ($serializeSim) { $serializeSim($job); });

        $this->app->instance('queue', $queueMock);

        $this->app->singleton('events', function () {
            return (new \MacropaySolutions\Kernel\Events\Dispatcher($this->app))
                ->setQueueResolver(fn () => $this->app->make('queue'));
        });

        /** @var Dispatcher $dispatcher */
        $dispatcher = $this->app->make('events');
        $dispatcher->listen(MessageSending::class, SampleQueuedListener::class);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('MessageSending events cannot be queued. They do not support JSON serialization.');

        $dispatcher->dispatch(new MessageSending(new Email()));
    }
}