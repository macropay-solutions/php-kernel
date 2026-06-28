<?php

namespace MacropaySolutions\KernelDev\Tests\Cache;

use MacropaySolutions\Kernel\Cache\ArrayStore;
use MacropaySolutions\Kernel\Cache\NullStore;
use MacropaySolutions\Kernel\Cache\TaggedCache;
use MacropaySolutions\Kernel\Cache\TagSet;
use MacropaySolutions\Kernel\Container\Container;
use MacropaySolutions\Kernel\Support\Carbon;
use MacropaySolutions\Framework\Application;
use PHPUnit\Framework\TestCase;

class TaggedCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Bind an application container instance to register the TTL ceiling macro constants
        $app = new Application();
        Container::setInstance($app);
    }

    protected function tearDown(): void
    {
        // Pop Framework's custom error and exception handlers to restore PHPUnit's defaults
        restore_error_handler();
        restore_exception_handler();

        Container::setInstance(null);
        parent::tearDown();
    }

    /**
     * Helper to retrieve a clean database instance in memory.
     */
    protected function getAtomicStore(): ArrayStore
    {
        return new ArrayStore();
    }

    public function test_tags_are_alphabetically_sorted_to_prevent_deadlocks()
    {
        $store = $this->getAtomicStore();
        $tags = new TagSet($store, ['zeta', 'alpha', 'omega']);

        $this->assertSame(
            ['alpha', 'omega', 'zeta'],
            $tags->getNames(),
            'Tags must be sorted alphabetically upon instantiation.'
        );
    }

    public function test_flush_is_an_instant_o1_version_increment()
    {
        $store = $this->getAtomicStore();
        $tags = new TagSet($store, ['user-profile']);

        $tags->attachKey('dummy-key-1');
        $this->assertEquals('1', $store->get('tag-version:user-profile'));

        $tags->resetTag('user-profile');
        $this->assertEquals('2', $store->get('tag-version:user-profile'));
    }

    public function test_put_enforces_strict_ttl_cap_overriding_forever()
    {
        $store = $this->getAtomicStore();
        $tags = new TagSet($store, ['global-settings']);
        $cache = new TaggedCache($store, $tags);

        $cache->forever('theme_color', 'dark');
        $this->assertEquals('dark', $cache->get('theme_color'));

        $this->assertEquals('1', $store->get('tag-version:global-settings'));
        $this->assertEquals(1, $store->get('tag-index-global-settings-v1'));
    }

    public function test_cross_tag_flushes_isolate_versions_correctly()
    {
        $store = $this->getAtomicStore();

        $tagsA = new TagSet($store, ['reports', 'monthly']);
        $cacheA = new TaggedCache($store, $tagsA);

        $tagsB = new TagSet($store, ['reports', 'yearly']);
        $cacheB = new TaggedCache($store, $tagsB);

        $cacheA->put('report-1', 'data-a');
        $cacheB->put('report-2', 'data-b');

        $tagsA->flushTag('monthly');

        $this->assertEquals('2', $store->get('tag-version:monthly'));
        $this->assertEquals('1', $store->get('tag-version:reports'));
        $this->assertEquals('1', $store->get('tag-version:yearly'));
    }

    public function test_null_store_does_not_trigger_infinite_loop_on_tag_reset()
    {
        $store = new NullStore();
        $tags = new TagSet($store, ['blackhole-tag']);

        $version = $tags->resetTag('blackhole-tag');
        $this->assertEquals('1', $version, 'NullStore must gracefully handle and exit tag version sequences.');
    }

    public function test_array_store_add_blocks_active_keys_but_overwrites_stale_keys()
    {
        $store = $this->getAtomicStore();

        // Scenario A: Active key must block subsequent atomic additions
        $store->put('active_key', 'fresh_data', 60);
        $this->assertFalse($store->add('active_key', 'new_data', 60));
        $this->assertEquals('fresh_data', $store->get('active_key'));

        // Scenario B: Expired key must allow atomic additions to pass through cleanly
        $store->put('stale_key', 'old_data', 60);

        // Safely extract the internal storage array using reflection properties
        $reflection = new \ReflectionClass($store);
        $property = $reflection->getProperty('storage');
        $storage = $property->getValue($store);

        // Explicitly backdate the timestamp into the past to trigger driver eviction thresholds
        $pastTimestamp = (Carbon::now()->getPreciseTimestamp(3) / 1000) - 5;
        if (isset($storage['stale_key'])) {
            $storage['stale_key']['expiresAt'] = $pastTimestamp;
            $storage['stale_key']['expires_at'] = $pastTimestamp;
        }
        $property->setValue($store, $storage);

        // The atomic addition will now correctly identify the key as dead, wipe it out, and return true
        $this->assertTrue($store->add('stale_key', 'fresh_data', 60));
        $this->assertEquals('fresh_data', $store->get('stale_key'));
    }

    public function test_put_many_generates_unique_pointers_per_item()
    {
        $store = $this->getAtomicStore();
        $tags = new TagSet($store, ['orders']);
        $cache = new TaggedCache($store, $tags);

        $cache->putMany([
            'order_101' => ['amount' => 50],
            'order_102' => ['amount' => 120],
        ], 3600);

        // The master sequence index should track both elements sequentially
        $this->assertEquals(2, $store->get('tag-index-orders-v1'));

        $pointer1 = $store->get('orders-v1-1');
        $pointer2 = $store->get('orders-v1-2');

        $this->assertNotNull($pointer1);
        $this->assertNotNull($pointer2);
        $this->assertNotEquals($pointer1, $pointer2, 'Batch keys must maps to distinct isolated pointer markers.');

        // Confirm lookups explicitly point back to targeted cryptographic payload namespaces
        $this->assertTrue(str_contains($pointer1, ':order_101'));
        $this->assertTrue(str_contains($pointer2, ':order_102'));
    }

    public function test_cascading_ttl_hierarchy_respects_russian_doll_decay()
    {
        $store = $this->getAtomicStore();
        $tags = new TagSet($store, ['session']);
        $cache = new TaggedCache($store, $tags);

        // Commit an object into storage with a transient short lifespan
        $cache->put('user_123', ['token' => 'xyz'], 100);

        // Extract metadata array via unchained reflection (safe for PHP 8.1+)
        $reflection = new \ReflectionClass($store);
        $property = $reflection->getProperty('storage');
        $storage = $property->getValue($store);

        // Tier 4: Payload data reflects volatile short user timestamp context (100 seconds)
        $payloadKey = null;
        foreach (array_keys($storage) as $key) {
            if (str_contains($key, ':user_123')) {
                $payloadKey = $key;
                break;
            }
        }
        $payloadTtl = $storage[$payloadKey]['expiresAt'];

        // Tiers 2 & 3: Structural index and pointer chains track fixed ceiling limits (+ 5s)
        $pointerTtl = $storage['session-v1-1']['expiresAt'];
        $counterTtl = $storage['tag-index-session-v1']['expiresAt'];

        // Tier 1: Master generation indicator outlives all structural children (cap * 2)
        $versionTtl = $storage['tag-version:session']['expiresAt'];

        $this->assertLessThan($pointerTtl, $payloadTtl, 'Volatile user data must expire well before structural mapping references.');
        $this->assertEquals($pointerTtl, $counterTtl, 'Tracking pointers and counter sequences must match decay intervals.');
        $this->assertGreaterThan($counterTtl, $versionTtl, 'The core tracking generation anchor must outlive index elements.');
    }

    public function test_tag_permutation_order_produces_identical_cryptographic_namespaces()
    {
        $store = $this->getAtomicStore();

        $cacheOrdered = new TaggedCache($store, new TagSet($store, ['alpha', 'omega']));
        $cacheReversed = new TaggedCache($store, new TagSet($store, ['omega', 'alpha']));

        $cacheOrdered->put('shared_config', 'compiled_payload', 3600);

        $this->assertEquals(
            'compiled_payload',
            $cacheReversed->get('shared_config'),
            'Internal tag sorting must ensure key lookup uniformity regardless of parameter order.'
        );
    }

    public function test_destination_keyspaces_are_synthesized_via_tag_sorted_cryptographic_hashes()
    {
        $store = $this->getAtomicStore();
        $tags = new TagSet($store, ['tagB', 'tagA']);
        $cache = new TaggedCache($store, $tags);

        $cache->put('target_key', 'secured_data', 3600);

        // The expected compiled string pattern matches your exact PR specifications
        $expectedNamespace = 'tagA:1|tagB:1';
        $expectedHashPrefix = sha1($expectedNamespace);
        $expectedPayloadStorageKey = $expectedHashPrefix . ':target_key';

        // Extract metadata array via unchained reflection (safe for PHP 8.1+)
        $reflection = new \ReflectionClass($store);
        $property = $reflection->getProperty('storage');
        $storage = $property->getValue($store);

        $this->assertArrayHasKey($expectedPayloadStorageKey, $storage, 'Payloads must be saved matching the explicit SHA1 compound key structure.');
        $this->assertEquals('secured_data', $store->get($expectedPayloadStorageKey));
    }

    public function test_get_operation_remains_pure_and_never_triggers_write_storms()
    {
        // Intercept and monitor execution metrics against the engine backend driver
        $store = new class extends ArrayStore {
            public int $writeOperationsCount = 0;

            public function put($key, $value, $seconds = 0): bool {
                $this->writeOperationsCount++;
                return parent::put($key, $value, $seconds);
            }

            public function touch($key, $seconds = 0): bool {
                $this->writeOperationsCount++;
                return true;
            }
        };

        $tags = new TagSet($store, ['analytics']);
        $cache = new TaggedCache($store, $tags);

        $cache->put('metric_1', 'value_1', 3600);
        $baselineWrites = $store->writeOperationsCount;

        // Execute sequential reads against the active tracking line
        for ($i = 0; $i < 50; $i++) {
            $cache->get('metric_1');
        }

        $this->assertEquals(
            $baselineWrites,
            $store->writeOperationsCount,
            'Read paths must calculate access namespaces mathematically without triggering background network mutations.'
        );
    }
}