<?php

namespace MacropaySolutions\Kernel\Tests\Cache;

use MacropaySolutions\Kernel\Cache\DatabaseLock;
use MacropaySolutions\Kernel\Database\QueryException;
use MacropaySolutions\Kernel\Database\SQLiteConnection;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * A failed lock INSERT means "taken" only when the lock row exists; other errors must not look like a held lock.
 */
class DatabaseLockErrorsTest extends TestCase
{
    private SQLiteConnection $connection;

    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE cache_locks (key VARCHAR PRIMARY KEY CHECK (length(key) <= 10), '
            . 'owner VARCHAR NOT NULL, expiration INTEGER NOT NULL)'
        );
        $this->connection = new SQLiteConnection($pdo, config: ['name' => 'sqlite']);
    }

    public function testHeldLockIsNotAcquired(): void
    {
        $this->assertTrue($this->lock('job', 'one')->acquire());
        $this->assertFalse($this->lock('job', 'two')->acquire());
    }

    public function testExpiredLockIsAcquired(): void
    {
        $this->connection->table('cache_locks')->insert([
            'key' => 'job',
            'owner' => 'one',
            'expiration' => \time() - 10,
        ]);

        $this->assertTrue($this->lock('job', 'two')->acquire());
    }

    public function testOtherInsertErrorsAreRethrown(): void
    {
        $this->expectException(QueryException::class);

        $this->lock('name-longer-than-ten', 'one')->acquire();
    }

    private function lock(string $name, string $owner): DatabaseLock
    {
        return new DatabaseLock($this->connection, 'cache_locks', $name, 10, $owner, [0, 100]);
    }
}
