<?php

namespace MacropaySolutions\Kernel\Tests\Feature\Database;

use MacropaySolutions\Kernel\Database\MySqlConnection;
use MacropaySolutions\Kernel\Database\Query\Builder;
use PHPUnit\Framework\TestCase;

/**
 * Updates and deletes with joins keep their ORDER BY and LIMIT instead of silently dropping them,
 * so a limited statement can never touch more rows than asked for.
 */
class MySqlGrammarSafetyTest extends TestCase
{
    public function test_an_update_with_a_join_keeps_its_order_and_limit(): void
    {
        $query = $this->query()->from('orders')
            ->join('users', 'users.id', '=', 'orders.user_id')
            ->where('users.active', 0)
            ->orderBy('orders.id')
            ->limit(10);

        $sql = $query->getGrammar()->compileUpdate($query, ['orders.status' => 'cancelled']);

        $this->assertStringContainsString('inner join `users`', $sql);
        $this->assertStringEndsWith('order by `orders`.`id` asc limit 10', $sql);
    }

    public function test_a_delete_with_a_join_keeps_its_limit(): void
    {
        $query = $this->query()->from('orders')
            ->join('users', 'users.id', '=', 'orders.user_id')
            ->where('users.active', 0)
            ->limit(5);

        $sql = $query->getGrammar()->compileDelete($query);

        $this->assertStringContainsString('inner join `users`', $sql);
        $this->assertStringEndsWith('limit 5', $sql);
    }

    public function test_an_update_without_a_join_keeps_its_limit(): void
    {
        $query = $this->query()->from('orders')->where('status', 'new')->limit(3);

        $this->assertStringEndsWith('limit 3', $query->getGrammar()->compileUpdate($query, ['status' => 'old']));
    }

    private function query(): Builder
    {
        $connection = new MySqlConnection(static function (): never {
            throw new \LogicException('No PDO is needed to compile SQL.');
        }, 'shop');

        return $connection->query();
    }
}
