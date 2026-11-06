<?php

namespace MacropaySolutions\Kernel\Tests\Feature\Cache;

use Carbon\Carbon;
use MacropaySolutions\Kernel\Cache\Repository;
use MacropaySolutions\Kernel\Contracts\Cache\LockTimeoutException;
use MacropaySolutions\Kernel\Filesystem\Filesystem;
use MacropaySolutions\Kernel\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The same repository behaviour on the array store and on the file store.
 */
class CacheStoresTest extends TestCase
{
    private string $directory;

    private Carbon $testNow;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testNow = Carbon::now();
        $this->directory = \sys_get_temp_dir() . '/kernel-cache-' . \bin2hex(\random_bytes(6));
        $this->container->instance('files', new Filesystem());
        $this->container->make('config')->set('cache.stores.file', [
            'driver' => 'file',
            'path' => $this->directory,
            'lock_path' => $this->directory,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow($this->testNow);
        (new Filesystem())->deleteDirectory($this->directory);

        parent::tearDown();
    }

    public static function stores(): array
    {
        return [
            'array' => ['array'],
            'file' => ['file'],
        ];
    }

    #[DataProvider('stores')]
    public function test_put_get_has_and_forget(string $store): void
    {
        $cache = $this->store($store);

        $this->assertNull($cache->get('missing'));
        $this->assertSame('default', $cache->get('missing', 'default'));
        $this->assertTrue($cache->put('name', ['first' => 'Ana'], 60));
        $this->assertSame(['first' => 'Ana'], $cache->get('name'));
        $this->assertTrue($cache->has('name'));
        $this->assertTrue($cache->forget('name'));
        $this->assertFalse($cache->has('name'));
    }

    #[DataProvider('stores')]
    public function test_values_expire_after_their_ttl(string $store): void
    {
        $cache = $this->store($store);
        $cache->put('token', 'abc', 10);

        $this->travelSeconds(9);
        $this->assertSame('abc', $cache->get('token'));

        $this->travelSeconds(2);
        $this->assertNull($cache->get('token'));
    }

    #[DataProvider('stores')]
    public function test_a_zero_or_negative_ttl_removes_the_value(string $store): void
    {
        $cache = $this->store($store);
        $cache->forever('flag', true);

        $this->assertTrue($cache->put('flag', false, 0), 'A zero TTL forgets the key.');
        $this->assertFalse($cache->has('flag'));
    }

    #[DataProvider('stores')]
    public function test_add_only_writes_a_missing_key(string $store): void
    {
        $cache = $this->store($store);

        $this->assertTrue($cache->add('slot', 'first', 60));
        $this->assertFalse($cache->add('slot', 'second', 60));
        $this->assertSame('first', $cache->get('slot'));
    }

    #[DataProvider('stores')]
    public function test_increment_and_decrement(string $store): void
    {
        $cache = $this->store($store);
        $cache->put('hits', 1, 60);

        $this->assertSame(3, $cache->increment('hits', 2));
        $this->assertSame(2, $cache->decrement('hits'));
        $this->assertSame(2, (int)$cache->get('hits'));
    }

    #[DataProvider('stores')]
    public function test_remember_runs_the_callback_once(string $store): void
    {
        $cache = $this->store($store);
        $calls = 0;
        $callback = function () use (&$calls): string {
            $calls++;

            return 'computed';
        };

        $this->assertSame('computed', $cache->remember('report', 60, $callback));
        $this->assertSame('computed', $cache->remember('report', 60, $callback));
        $this->assertSame('computed', $cache->rememberForever('forever-report', $callback));
        $this->assertSame(2, $calls);
    }

    #[DataProvider('stores')]
    public function test_pull_returns_and_removes(string $store): void
    {
        $cache = $this->store($store);
        $cache->put('once', 'value', 60);

        $this->assertSame('value', $cache->pull('once'));
        $this->assertNull($cache->get('once'));
    }

    #[DataProvider('stores')]
    public function test_many_and_put_many(string $store): void
    {
        $cache = $this->store($store);

        $this->assertTrue($cache->putMany(['a' => 1, 'b' => 2], 60));
        $this->assertSame(['a' => 1, 'b' => 2, 'c' => null], $cache->many(['a', 'b', 'c']));
    }

    #[DataProvider('stores')]
    public function test_flush_removes_everything(string $store): void
    {
        $cache = $this->store($store);
        $cache->put('a', 1, 60);
        $cache->forever('b', 2);

        $this->assertTrue($cache->clear());
        $this->assertNull($cache->get('a'));
        $this->assertNull($cache->get('b'));
    }

    #[DataProvider('stores')]
    public function test_a_lock_can_be_held_by_one_owner_only(string $store): void
    {
        $lockStore = $this->store($store)->getStore();
        $first = $lockStore->lock('invoice', 10);
        $second = $lockStore->lock('invoice', 10);

        $this->assertTrue($first->get());
        $this->assertFalse($second->get());
        $this->assertFalse($second->release(), 'Only the owner can release the lock.');
        $this->assertTrue($first->release());
        $this->assertTrue($second->get());
        $second->release();
    }

    #[DataProvider('stores')]
    public function test_a_lock_can_be_restored_by_its_owner_token(string $store): void
    {
        $lockStore = $this->store($store)->getStore();
        $lock = $lockStore->lock('export', 10);
        $lock->get();

        $restored = $lockStore->restoreLock('export', $lock->owner());

        $this->assertTrue($restored->release());
        $this->assertTrue($lockStore->lock('export', 10)->get());
    }

    #[DataProvider('stores')]
    public function test_a_lock_runs_the_callback_and_releases(string $store): void
    {
        $lockStore = $this->store($store)->getStore();

        $this->assertSame('done', $lockStore->lock('job', 10)->get(fn(): string => 'done'));
        $this->assertTrue($lockStore->lock('job', 10)->get());
    }

    #[DataProvider('stores')]
    public function test_a_lock_expires_after_its_seconds(string $store): void
    {
        $lockStore = $this->store($store)->getStore();
        $lockStore->lock('short', 5)->get();

        $this->travelSeconds(6);

        $this->assertTrue($lockStore->lock('short', 5)->get());
    }

    public function test_block_throws_when_the_lock_is_not_freed_in_time(): void
    {
        $lockStore = $this->store('array')->getStore();
        $lockStore->lock('busy', 10)->get();

        $this->expectException(LockTimeoutException::class);

        $lockStore->lock('busy', 10)->betweenBlockedAttemptsSleepFor(1)->block(0);
    }

    public function test_the_file_store_survives_a_new_repository(): void
    {
        $this->store('file')->put('shared', 'kept', 60);

        $this->assertSame('kept', $this->freshCacheManagerStore('file')->get('shared'));
    }

    private function store(string $name): Repository
    {
        return $this->cache->store($name);
    }

    private function freshCacheManagerStore(string $name): Repository
    {
        return (new \MacropaySolutions\Kernel\Cache\CacheManager($this->container))->store($name);
    }

    private function travelSeconds(int $seconds): void
    {
        Carbon::setTestNow(Carbon::now()->addSeconds($seconds));
    }
}
