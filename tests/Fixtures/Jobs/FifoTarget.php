<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Jobs;

class FifoTarget
{
    public static function messageGroup(array $args): string
    {
        return 'static-group-' . ($args['id'] ?? '');
    }

    public static function deduplicationId(array $args): string
    {
        return 'static-dedup-' . ($args['id'] ?? '');
    }

    public function handle(int $id): void
    {
    }
}
