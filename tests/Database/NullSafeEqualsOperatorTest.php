<?php

namespace MacropaySolutions\Kernel\Tests\Database;

use MacropaySolutions\Kernel\Database\MySqlConnection;
use MacropaySolutions\Kernel\Database\SQLiteConnection;
use PHPUnit\Framework\TestCase;

class NullSafeEqualsOperatorTest extends TestCase
{
    public function testNullSafeEqualsWithNullIsIsNull(): void
    {
        $mysql = new MySqlConnection(new \PDO('sqlite::memory:'), 'app', '', ['driver' => 'mysql']);
        $query = $mysql->table('users')->where('deleted_by', '<=>', null)->orWhere('id', '<>', null);

        $this->assertSame(
            'select * from `users` where `deleted_by` is null or `id` is not null',
            $query->toSql()
        );
    }

    public function testItRunsOnSqlite(): void
    {
        $sqlite = new SQLiteConnection(new \PDO('sqlite::memory:'), ':memory:', '', ['driver' => 'sqlite']);
        $sqlite->statement('create table users (id integer primary key, deleted_by int)');
        $sqlite->table('users')->insert([['id' => 1, 'deleted_by' => null], ['id' => 2, 'deleted_by' => 5]]);

        $this->assertSame([1], $sqlite->table('users')->where('deleted_by', '<=>', null)->pluck('id')->all());
    }
}
