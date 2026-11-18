<?php

namespace MacropaySolutions\Kernel\Tests\Database;

use MacropaySolutions\Kernel\Database\SQLiteConnection;
use PHPUnit\Framework\TestCase;

class PaginationCountDistinctTest extends TestCase
{
    public function testThePaginationTotalMatchesTheDistinctRows(): void
    {
        $connection = new SQLiteConnection(new \PDO('sqlite::memory:'));
        $connection->statement('create table t (a int, b int)');
        $connection->table('t')->insert([['a' => 1, 'b' => 1], ['a' => 1, 'b' => 1], ['a' => 2, 'b' => 3]]);

        foreach (
            [
                $connection->table('t')->select('a', 'b')->distinct(),
                $connection->table('t')->distinct(),
                $connection->table('t')->select('a')->distinct()->where('b', '>', 0)->orderBy('a'),
                $connection->table('t')->distinct('a'),
                $connection->table('t'),
            ] as $query
        ) {
            $this->assertSame((clone $query)->get()->count(), $query->getCountForPagination(), $query->toSql());
        }
    }
}
