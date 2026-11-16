<?php

namespace MacropaySolutions\Kernel\Tests\Database;

use MacropaySolutions\Kernel\Cache\ArrayStore;
use MacropaySolutions\Kernel\Cache\DatabaseStore;
use MacropaySolutions\Kernel\Cache\Repository;
use MacropaySolutions\Kernel\Config\Repository as Config;
use MacropaySolutions\Kernel\Container\Container;
use MacropaySolutions\Kernel\Contracts\Cache\Lock;
use MacropaySolutions\Kernel\Contracts\Cache\Store;
use MacropaySolutions\Kernel\Database\Console\Migrations\MigrateCommand;
use PHPUnit\Framework\TestCase;

/**
 * With the database cache store the migration lock needs a table that may not exist yet (fresh install) or that
 * db:wipe drops (migrate:fresh): migrate runs without the lock there.
 */
class MigrationLockStoreTest extends TestCase
{
    public function testNoLockWithTheDatabaseCacheStore(): void
    {
        $store = (new \ReflectionClass(DatabaseStore::class))->newInstanceWithoutConstructor();

        $this->assertNull($this->lockFor($store));
    }

    public function testOtherStoresLock(): void
    {
        $store = new ArrayStore();
        $lock = $this->lockFor($store);

        $this->assertTrue($lock instanceof Lock);
        $this->assertTrue($lock->get());
        $this->assertFalse($this->lockFor($store)->get());
    }

    private function lockFor(Store $store): ?Lock
    {
        $app = new Container();
        $app->instance('config', new Config(['database' => ['migrations_lock_ttl' => 300]]));
        $app->instance('cache', new class (new Repository($store)) {
            public function __construct(private Repository $repository)
            {
            }

            public function store(): Repository
            {
                return $this->repository;
            }
        });

        $command = (new \ReflectionClass(MigrateCommand::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty($command, 'app'))->setValue($command, $app);

        return (new \ReflectionMethod($command, 'getMigrationLock'))->invoke($command);
    }
}
