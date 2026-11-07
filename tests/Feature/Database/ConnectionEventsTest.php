<?php

namespace MacropaySolutions\Kernel\Tests\Feature\Database;

use MacropaySolutions\Kernel\Database\Connection;
use MacropaySolutions\Kernel\Database\Events\QueryExecuted;
use MacropaySolutions\Kernel\Tests\ApplicationTestCase;
use MacropaySolutions\Kernel\Tests\Fixtures\Database\RecordQueries;

/**
 * The sqlite connection is opened in setUp, before anything has resolved the event dispatcher.
 */
class ConnectionEventsTest extends ApplicationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        RecordQueries::$queries = [];
    }

    public function test_db_listen_registers_a_query_executed_listener_class(): void
    {
        $this->app->make('db')->listen(RecordQueries::class);

        $this->db()->select('select 1');
        $this->db()->select('select 2');

        $this->assertSame(['select 1', 'select 2'], RecordQueries::$queries);
    }

    public function test_db_listen_accepts_an_array_callable(): void
    {
        $this->db()->listen([RecordQueries::class, 'handle']);

        $this->db()->select('select 1');

        $this->assertSame(['select 1'], RecordQueries::$queries);
    }

    public function test_query_executed_listeners_receive_queries_from_a_connection_opened_earlier(): void
    {
        $this->app->make('events')->listen(QueryExecuted::class, RecordQueries::class);

        $this->db()->select('select 3');

        $this->assertSame(['select 3'], RecordQueries::$queries);
    }

    public function test_when_querying_for_longer_than_runs_its_handler_once_past_the_threshold(): void
    {
        $calls = [];

        $this->db()->whenQueryingForLongerThan(8, function (Connection $connection, QueryExecuted $event) use (&$calls): void {
            $calls[] = $event->sql;
        });

        $this->db()->logQuery('select 1', [], 5.0);
        $this->db()->logQuery('select 2', [], 5.0);
        $this->db()->logQuery('select 3', [], 5.0);

        $this->assertSame(['select 2'], $calls);
    }

    public function test_query_duration_handlers_can_run_again_after_being_reset(): void
    {
        $calls = 0;

        $this->db()->whenQueryingForLongerThan(1, function () use (&$calls): void {
            $calls++;
        });

        $this->db()->logQuery('select 1', [], 5.0);
        $this->db()->allowQueryDurationHandlersToRunAgain();
        $this->db()->logQuery('select 2', [], 5.0);

        $this->assertSame(2, $calls);
    }
}
