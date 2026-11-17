<?php

namespace MacropaySolutions\Kernel\Tests\Console;

use MacropaySolutions\Kernel\Cache\ArrayStore;
use MacropaySolutions\Kernel\Cache\DatabaseLock;
use MacropaySolutions\Kernel\Cache\FileStore;
use MacropaySolutions\Kernel\Cache\NoLock;
use MacropaySolutions\Kernel\Cache\Repository;
use MacropaySolutions\Kernel\Console\Scheduling\CacheEventMutex;
use MacropaySolutions\Kernel\Console\Scheduling\Event;
use MacropaySolutions\Kernel\Contracts\Cache\Factory;
use MacropaySolutions\Kernel\Contracts\Cache\LockProvider;
use MacropaySolutions\Kernel\Contracts\Cache\Repository as RepositoryContract;
use MacropaySolutions\Kernel\Database\SQLiteConnection;
use MacropaySolutions\Kernel\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;

class CacheEventMutexIsLockedTest extends TestCase
{
    public function testExistsDoesNotAcquireTheLock(): void
    {
        $store = new CacheEventMutexIsLockedTestStore();
        $mutex = new CacheEventMutex(new CacheEventMutexIsLockedTestFactory(new Repository($store)));
        $event = new Event($mutex, 'inspire');

        $this->assertFalse($mutex->exists($event));
        $this->assertSame(0, $store->acquired);

        $this->assertTrue($mutex->create($event));
        $this->assertTrue($mutex->exists($event));
        $this->assertSame(1, $store->acquired);
    }

    public function testArrayLocks(): void
    {
        $store = new ArrayStore();
        $lock = $store->lock('a', 1);

        $this->assertFalse($lock->isLocked());
        $this->assertTrue($lock->acquire());
        $this->assertTrue($store->lock('a')->isLocked());

        $store->locks['a']['expiresAt'] = $store->locks['a']['expiresAt']->subSeconds(5);

        $this->assertFalse($store->lock('a')->isLocked());
    }

    public function testFileLocks(): void
    {
        $directory = \sys_get_temp_dir() . '/is-locked-' . \uniqid();
        $store = new FileStore(new Filesystem(), $directory);

        try {
            $this->assertFalse($store->lock('f', 10)->isLocked());
            $this->assertTrue($store->lock('f', 10)->acquire());
            $this->assertTrue($store->lock('f', 10)->isLocked());
        } finally {
            (new Filesystem())->deleteDirectory($directory);
        }
    }

    public function testDatabaseLocks(): void
    {
        $connection = new SQLiteConnection(new \PDO('sqlite::memory:'), config: ['name' => 'sqlite']);
        $connection->statement('create table cache_locks (key varchar primary key, owner varchar, expiration integer)');
        $lock = static fn() => new DatabaseLock($connection, 'cache_locks', 'd', 10, null, [0, 100]);

        $this->assertFalse($lock()->isLocked());
        $this->assertTrue($lock()->acquire());
        $this->assertTrue($lock()->isLocked());

        $connection->table('cache_locks')->update(['expiration' => \time() - 1]);

        $this->assertFalse($lock()->isLocked());
    }

    public function testNoLockIsNeverLocked(): void
    {
        $this->assertFalse((new NoLock('n', 10))->isLocked());
    }
}

class CacheEventMutexIsLockedTestFactory implements Factory
{
    public function __construct(private RepositoryContract $repository)
    {
    }

    public function store($name = null)
    {
        return $this->repository;
    }

    public function fwd(): RepositoryContract|LockProvider
    {
        return $this->repository;
    }
}

/**
 * An array store that counts lock acquisitions.
 */
class CacheEventMutexIsLockedTestStore extends ArrayStore
{
    public int $acquired = 0;

    public function lock($name, $seconds = 0, $owner = null)
    {
        return new CacheEventMutexIsLockedTestLock($this, $name, $seconds, $owner);
    }
}

class CacheEventMutexIsLockedTestLock extends \MacropaySolutions\Kernel\Cache\ArrayLock
{
    public function acquire()
    {
        $this->store->acquired++;

        return parent::acquire();
    }
}
