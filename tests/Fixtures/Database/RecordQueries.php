<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Database;

use MacropaySolutions\Kernel\Database\Events\QueryExecuted;

class RecordQueries
{
    /**
     * @var string[]
     */
    public static array $queries = [];

    public function handle(QueryExecuted $query): void
    {
        static::$queries[] = $query->sql;
    }
}
