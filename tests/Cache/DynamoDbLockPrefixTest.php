<?php

namespace MacropaySolutions\Kernel\Tests\Cache;

use MacropaySolutions\Kernel\Cache\DynamoDbStore;
use PHPUnit\Framework\TestCase;

/**
 * A DynamoDB lock name was prefixed by lock() and again by the store's add() / get() / forget(): the item key had
 * the prefix twice.
 */
class DynamoDbLockPrefixTest extends TestCase
{
    public function testTheLockNameIsNotPrefixedTwice(): void
    {
        $store = (new \ReflectionClass(DynamoDbStore::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty($store, 'prefix'))->setValue($store, 'app:');

        $lock = $store->lock('report', 10);

        $this->assertSame('report', (new \ReflectionProperty($lock, 'name'))->getValue($lock));
    }
}
