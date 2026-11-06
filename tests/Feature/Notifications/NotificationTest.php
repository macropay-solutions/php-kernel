<?php

namespace MacropaySolutions\Kernel\Tests\Feature\Notifications;

use MacropaySolutions\Kernel\Contracts\Notifications\Dispatcher;
use MacropaySolutions\Kernel\Notifications\AnonymousNotifiable;
use MacropaySolutions\Kernel\Notifications\ChannelManager;
use MacropaySolutions\Kernel\Tests\ApplicationTestCase;
use MacropaySolutions\Kernel\Tests\Fixtures\Notifications\AuditChannel;
use MacropaySolutions\Kernel\Tests\Fixtures\Notifications\Customer;
use MacropaySolutions\Kernel\Tests\Fixtures\Notifications\InvoicePaid;
use MacropaySolutions\Kernel\Tests\Fixtures\Notifications\QueuedInvoicePaid;
use MacropaySolutions\Kernel\Tests\Fixtures\Notifications\SmsChannel;

/**
 * Notifications through the real channel manager: channel classes, extended drivers, on-demand routes and the queue.
 */
class NotificationTest extends ApplicationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        SmsChannel::$sent = [];
        AuditChannel::$logged = [];
        $this->app->make(ChannelManager::class)->extend('audit', \Closure::fromCallable([AuditChannel::class, 'make']));
    }

    public function test_a_notification_is_sent_on_every_channel_it_asks_for(): void
    {
        (new Customer())->notify(new InvoicePaid(12));

        $this->assertSame(['+40700000000:Invoice 12 paid'], SmsChannel::$sent);
        $this->assertSame([InvoicePaid::class . '#12'], AuditChannel::$logged);
    }

    public function test_on_demand_notifications_use_the_given_routes(): void
    {
        $this->dispatcher()->send((new AnonymousNotifiable())->route('sms', '+40711111111'), new InvoicePaid(5));

        $this->assertSame(['+40711111111:Invoice 5 paid'], SmsChannel::$sent);
    }

    public function test_send_now_delivers_a_should_queue_notification_immediately(): void
    {
        $this->dispatcher()->sendNow(new Customer(), new QueuedInvoicePaid(4));

        $this->assertSame(['+40700000000:Invoice 4 paid'], SmsChannel::$sent);
        $this->assertSame(0, $this->db()->table('jobs')->count());
    }

    public function test_a_should_queue_notification_is_delivered_by_the_worker(): void
    {
        $this->dispatcher()->send((new AnonymousNotifiable())->route('sms', '+40722222222'), new QueuedInvoicePaid(3));

        $this->assertSame([], SmsChannel::$sent);
        $this->assertSame(1, $this->db()->table('jobs')->count());

        $this->artisan('queue:work', ['connection' => 'database', '--sleep' => 0]);

        $this->assertSame(['+40722222222:Invoice 3 paid'], SmsChannel::$sent);
        $this->assertSame(0, $this->db()->table('failed_jobs')->count());
    }

    /**
     * A notifiable that cannot be stored in the payload used to be dropped, so the queued job delivered nothing.
     */
    public function test_queueing_for_a_notifiable_that_cannot_be_stored_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Queued notifications can only be sent to Obvious models');

        (new Customer())->notify(new QueuedInvoicePaid(3));
    }

    private function dispatcher(): Dispatcher
    {
        return $this->app->make(Dispatcher::class);
    }
}
