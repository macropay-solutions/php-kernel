<?php

namespace MacropaySolutions\Kernel\Tests\Feature\Broadcasting;

use MacropaySolutions\Kernel\Tests\ApplicationTestCase;
use MacropaySolutions\Kernel\Tests\Fixtures\Broadcasting\OrderUpdated;
use MacropaySolutions\Kernel\Tests\Fixtures\Broadcasting\OrderUpdatedNow;
use MacropaySolutions\Kernel\Tests\Fixtures\Log\RecordLogMessages;
use Monolog\Handler\NullHandler;

/**
 * Broadcast events go through the queue (or straight to the broadcaster with ShouldBroadcastNow) and reach the
 * configured broadcaster with their name, channels and payload. The log broadcaster makes that visible.
 */
class BroadcastingTest extends ApplicationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        RecordLogMessages::$messages = [];
        $this->app->configure('broadcasting');
        $this->app->configure('logging');
        $config = $this->app->make('config');
        $config->set('broadcasting.default', 'log');
        $config->set('logging.default', 'null');
        $config->set('logging.channels.null', ['driver' => 'monolog', 'handler' => NullHandler::class]);
        $this->app->make('log')->channel('null')->listen(RecordLogMessages::class);
    }

    public function test_a_broadcast_event_is_queued_and_broadcast_by_the_worker(): void
    {
        $this->app->make('events')->dispatch(new OrderUpdated(7, 'paid'));

        $this->assertSame([], RecordLogMessages::$messages);
        $this->assertSame(1, $this->db()->table('jobs')->count());

        $this->artisan('queue:work', ['connection' => 'database', '--sleep' => 0]);

        $this->assertCount(1, RecordLogMessages::$messages);
        $this->assertStringContainsString(
            'info:Broadcasting [order.updated] on channels [orders, private-user.7] with payload:',
            RecordLogMessages::$messages[0]
        );
        $this->assertStringContainsString('"status": "paid"', RecordLogMessages::$messages[0]);
    }

    public function test_should_broadcast_now_skips_the_queue(): void
    {
        $this->app->make('events')->dispatch(new OrderUpdatedNow(8, 'shipped'));

        $this->assertSame(0, $this->db()->table('jobs')->count());
        $this->assertCount(1, RecordLogMessages::$messages);
        $this->assertStringContainsString('[orders, private-user.8]', RecordLogMessages::$messages[0]);
    }

    public function test_broadcast_when_false_sends_nothing(): void
    {
        $this->app->make('events')->dispatch(new OrderUpdatedNow(9, 'draft', false));
        $this->app->make('events')->dispatch(new OrderUpdated(9, 'draft', false));

        $this->assertSame(0, $this->db()->table('jobs')->count());
        $this->assertSame([], RecordLogMessages::$messages);
    }
}
