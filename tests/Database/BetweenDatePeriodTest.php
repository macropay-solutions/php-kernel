<?php

namespace MacropaySolutions\Kernel\Tests\Database;

use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use MacropaySolutions\Kernel\Database\SQLiteConnection;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * A native DatePeriod was flattened like any iterable: whereBetween() bound its first two dates instead of its bounds.
 */
class BetweenDatePeriodTest extends TestCase
{
    private SQLiteConnection $connection;

    protected function setUp(): void
    {
        $this->connection = new SQLiteConnection(new PDO('sqlite::memory:'), config: ['name' => 'sqlite']);
    }

    public function testWhereBetweenBindsTheBoundsOfThePeriod(): void
    {
        $query = $this->connection->table('orders')->whereBetween('created_at', $this->period());

        $this->assertSame(['2024-01-01', '2024-01-31'], $this->dates($query->getBindings()));
    }

    public function testHavingBetweenBindsTheBoundsOfThePeriod(): void
    {
        $query = $this->connection->table('orders')->havingBetween('created_at', $this->period());

        $this->assertSame(['2024-01-01', '2024-01-31'], $this->dates($query->getRawBindings()['having']));
    }

    public function testPeriodWithRecurrencesBindsItsLastDate(): void
    {
        $period = new DatePeriod(new DateTimeImmutable('2024-01-01'), new DateInterval('P1D'), 3);
        $query = $this->connection->table('orders')->whereBetween('created_at', $period);

        $this->assertSame(['2024-01-01', '2024-01-04'], $this->dates($query->getBindings()));
    }

    private function period(): DatePeriod
    {
        return new DatePeriod(
            new DateTimeImmutable('2024-01-01'),
            new DateInterval('P1D'),
            new DateTimeImmutable('2024-01-31')
        );
    }

    public function testALongPeriodWithRecurrencesIsNotCollectedInMemory(): void
    {
        $period = new DatePeriod(new DateTimeImmutable('2024-01-01'), new DateInterval('PT1S'), 1000000);
        \memory_reset_peak_usage();
        $before = \memory_get_usage();

        $query = $this->connection->table('orders')->whereBetween('created_at', $period);

        $this->assertSame(['2024-01-01', '2024-01-12'], $this->dates($query->getBindings()));
        $this->assertLessThan(1024 * 1024, \memory_get_peak_usage() - $before);
    }

    private function dates(array $bindings): array
    {
        return \array_map(static fn(\DateTimeInterface $date): string => $date->format('Y-m-d'), $bindings);
    }
}
