<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Redis;

/**
 * Stands in for the Predis client: records the commands the connection passes down.
 */
class FakeRedisClient
{
    /**
     * @var array<int, array{0: string, 1: array}>
     */
    public array $commands = [];

    public function get(string $key): string
    {
        $this->commands[] = ['get', [$key]];

        return 'value-of-' . $key;
    }

    public function set(string $key, string $value): bool
    {
        $this->commands[] = ['set', [$key, $value]];

        return true;
    }
}
