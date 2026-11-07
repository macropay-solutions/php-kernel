<?php

namespace MacropaySolutions\Kernel\Tests\Feature\Events;

use PHPUnit\Framework\TestCase;

/**
 * event:cache writes the providers' listen maps to bootstrap/cache, and a fresh app takes its listeners from
 * that file. Each step runs in its own PHP process, as the cached file list is read when the app is created.
 */
class EventCacheTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = \sys_get_temp_dir() . '/event-cache-' . \bin2hex(\random_bytes(4));
        \mkdir($this->dir . '/bootstrap/cache', 0777, true);
    }

    protected function tearDown(): void
    {
        \exec('rm -rf ' . \escapeshellarg($this->dir));

        parent::tearDown();
    }

    public function test_a_fresh_app_takes_its_listeners_from_the_event_cache(): void
    {
        [$exitCode, $output] = $this->runStep('compile');

        $this->assertSame(0, $exitCode, $output);
        $this->assertFileExists($this->dir . '/bootstrap/cache/events.php');

        [$exitCode, $output] = $this->runStep('dispatch');

        $this->assertSame(0, $exitCode, $output);
        $this->assertSame(['cached' => true, 'log' => ['record:42']], \json_decode($output, true));
    }

    public function test_without_the_cache_the_empty_listen_map_registers_nothing(): void
    {
        [$exitCode, $output] = $this->runStep('dispatch');

        $this->assertSame(0, $exitCode, $output);
        $this->assertSame(['cached' => false, 'log' => []], \json_decode($output, true));
    }

    /**
     * @return array{int, string}
     */
    private function runStep(string $step): array
    {
        $command = \escapeshellarg(\PHP_BINARY) . ' '
            . \escapeshellarg(\dirname(__DIR__, 2) . '/Fixtures/EventCache/event-cache.php') . ' '
            . \escapeshellarg($step) . ' ' . \escapeshellarg($this->dir) . ' 2>&1';

        \exec($command, $lines, $exitCode);

        return [$exitCode, \implode("\n", $lines)];
    }
}
