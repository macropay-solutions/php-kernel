<?php

namespace MacropaySolutions\Kernel\Tests\Feature\Queue;

use PHPUnit\Framework\TestCase;

/**
 * queue:work runs one job per process and arms the job's timeout for it. A job that runs past its timeout with
 * failOnTimeout is failed with a TimeoutExceededException, removed from the queue, and the process exits.
 */
class WorkerTimeoutTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        if (!\extension_loaded('pcntl') || \DIRECTORY_SEPARATOR === '\\') {
            $this->markTestSkipped('Job timeouts need pcntl.');
        }

        $this->dir = \sys_get_temp_dir() . '/worker-timeout-' . \bin2hex(\random_bytes(4));
        \mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        if (isset($this->dir)) {
            \exec('rm -rf ' . \escapeshellarg($this->dir));
        }

        parent::tearDown();
    }

    public function test_a_job_running_past_its_timeout_is_failed_and_the_worker_exits(): void
    {
        $started = \microtime(true);

        [$exitCode, $output] = $this->work(10);

        $this->assertLessThan(8, \microtime(true) - $started, 'The worker must not wait for the job to finish.');
        $this->assertNotSame(0, $exitCode, $output);

        $pdo = new \PDO('sqlite:' . $this->dir . '/db.sqlite');

        $this->assertSame(0, (int)$pdo->query('select count(*) from jobs')->fetchColumn());

        $exception = (string)$pdo->query('select exception from failed_jobs')->fetchColumn();

        $this->assertStringContainsString('TimeoutExceededException', $exception, $output);
    }

    public function test_a_job_within_its_timeout_finishes_normally(): void
    {
        [$exitCode, $output] = $this->work(0);

        $this->assertSame(0, $exitCode, $output);

        $pdo = new \PDO('sqlite:' . $this->dir . '/db.sqlite');

        $this->assertSame(0, (int)$pdo->query('select count(*) from jobs')->fetchColumn());
        $this->assertSame(0, (int)$pdo->query('select count(*) from failed_jobs')->fetchColumn());
    }

    /**
     * @return array{int, string}
     */
    private function work(int $seconds): array
    {
        $command = \escapeshellarg(\PHP_BINARY) . ' '
            . \escapeshellarg(\dirname(__DIR__, 2) . '/Fixtures/Queue/timeout-worker.php') . ' '
            . \escapeshellarg($this->dir) . ' ' . $seconds . ' 2>&1';

        \exec($command, $lines, $exitCode);

        return [$exitCode, \implode("\n", $lines)];
    }
}
