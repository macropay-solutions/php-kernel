<?php

namespace MacropaySolutions\Kernel\Tests\Queue;

use Exception;
use MacropaySolutions\Kernel\Queue\Failed\FileFailedJobProvider;
use PHPUnit\Framework\TestCase;

/**
 * A failed job whose payload has no uuid (raw payloads, invalid JSON) must still be recorded.
 */
class FailedJobUuidTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = \tempnam(\sys_get_temp_dir(), 'failed-jobs');
        \unlink($this->path);
    }

    protected function tearDown(): void
    {
        \is_file($this->path) && \unlink($this->path);
    }

    public function testPayloadUuidIsUsed(): void
    {
        $provider = new FileFailedJobProvider($this->path);

        $this->assertSame('abc', $provider->log('sqs', 'default', '{"uuid":"abc"}', new Exception('failed')));
    }

    public function testPayloadsWithoutUuidAreRecorded(): void
    {
        $provider = new FileFailedJobProvider($this->path);
        $ids = [];

        foreach (['not json', '{"job":"raw"}', '{"uuid":""}', '5', '{"uuid":[1]}'] as $payload) {
            $ids[] = $provider->log('sqs', 'default', $payload, new Exception('failed'));
        }

        foreach ($ids as $id) {
            $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $id);
        }

        $this->assertCount(5, $provider->all());
    }
}
