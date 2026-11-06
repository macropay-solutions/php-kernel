<?php

namespace MacropaySolutions\Kernel\Tests\Feature\Events;

use MacropaySolutions\Kernel\Contracts\Events\Dispatcher;
use MacropaySolutions\Kernel\Tests\ApplicationTestCase;
use MacropaySolutions\Kernel\Tests\Fixtures\Events\EventLog;
use MacropaySolutions\Kernel\Tests\Fixtures\Events\InventoryManager;
use MacropaySolutions\Kernel\Tests\Fixtures\Events\OrderShipped;
use MacropaySolutions\Kernel\Tests\Fixtures\Events\QueuedShipmentNotification;
use MacropaySolutions\Kernel\Tests\Fixtures\Events\RecordShipment;
use MacropaySolutions\Kernel\Tests\Fixtures\Events\StopPropagation;
use MacropaySolutions\Kernel\Tests\Fixtures\Events\WildcardListener;

use function MacropaySolutions\Kernel\Events\queueableArray;

class EventDispatcherTest extends ApplicationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        EventLog::$entries = [];
    }

    public function test_class_and_array_callable_listeners_run_in_registration_order(): void
    {
        $this->events()->listen(OrderShipped::class, RecordShipment::class);
        $this->events()->listen(OrderShipped::class, [InventoryManager::class, 'reserve']);

        $this->events()->dispatch(new OrderShipped(7));

        $this->assertSame(['record:7', 'reserve:7'], EventLog::$entries);
    }

    public function test_a_listener_returning_false_stops_the_remaining_listeners(): void
    {
        $this->events()->listen(OrderShipped::class, StopPropagation::class);
        $this->events()->listen(OrderShipped::class, RecordShipment::class);

        $this->events()->dispatch(new OrderShipped(1));

        $this->assertSame(['stop:1'], EventLog::$entries);
    }

    public function test_until_returns_the_first_non_null_response(): void
    {
        $this->events()->listen('question', [InventoryManager::class, 'answer']);

        $this->assertSame('answer to life', $this->events()->until('question', ['life']));
    }

    public function test_wildcard_listeners_get_the_event_name_and_payload(): void
    {
        $this->events()->listen('orders.*', WildcardListener::class);

        $this->events()->dispatch('orders.cancelled', [42]);
        $this->events()->dispatch('users.created', [1]);

        $this->assertSame(['wildcard:orders.cancelled:[42]'], EventLog::$entries);
    }

    public function test_forget_removes_the_listeners(): void
    {
        $this->events()->listen(OrderShipped::class, RecordShipment::class);
        $this->events()->forget(OrderShipped::class);

        $this->events()->dispatch(new OrderShipped(1));

        $this->assertFalse($this->events()->hasListeners(OrderShipped::class));
        $this->assertSame([], EventLog::$entries);
    }

    public function test_a_should_queue_listener_is_queued_and_run_by_the_worker_with_the_rebuilt_event(): void
    {
        $this->events()->listen(OrderShipped::class, QueuedShipmentNotification::class);

        $this->events()->dispatch(new OrderShipped(99, 'ups'));

        $this->assertSame([], EventLog::$entries, 'The listener does not run on dispatch.');
        $this->assertSame(1, $this->db()->table('jobs')->count());

        $this->artisan('queue:work', ['connection' => 'database', '--sleep' => 0]);

        $this->assertSame(['queued:99:ups'], EventLog::$entries);
        $this->assertSame(0, $this->db()->table('jobs')->count());
    }

    /**
     * The payload is json encoded, so queueable array listeners get primitive event arguments.
     */
    public function test_a_queueable_array_listener_is_queued_with_its_queue_and_delay(): void
    {
        $this->events()->listen(
            'stock.reserve',
            queueableArray([InventoryManager::class, 'reserveById'])->onQueue('stock')->delay(30)
        );

        $this->events()->dispatch('stock.reserve', [5]);

        $this->assertSame([], EventLog::$entries);
        $this->assertSame('stock', $this->db()->table('jobs')->value('queue'));

        $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'stock', '--sleep' => 0]);
        $this->assertSame([], EventLog::$entries, 'The delay has not passed yet.');

        $this->travelSeconds(31);
        $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'stock', '--sleep' => 0]);

        $this->assertSame(['reserve-by-id:5'], EventLog::$entries);
    }

    private function events(): Dispatcher
    {
        return $this->app->make('events');
    }
}
