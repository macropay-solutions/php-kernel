<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Database;

use MacropaySolutions\Kernel\Database\Obvious\Builder;
use MacropaySolutions\Kernel\Database\Obvious\Model;
use MacropaySolutions\Kernel\Database\Obvious\Prunable;

class AuditLog extends Model
{
    use Prunable;

    public static array $pruned = [];

    protected string $table = 'audit_logs';

    public bool $timestamps = false;

    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', '2026-01-01 00:00:00');
    }

    protected function pruning(): void
    {
        static::$pruned[] = $this->getAttributeValue('id');
    }
}
