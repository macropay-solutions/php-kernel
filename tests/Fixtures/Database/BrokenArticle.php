<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Database;

use MacropaySolutions\Kernel\Database\Obvious\Model;

/**
 * Uses everything the strict model forbids: non-primitive casts, object accessors and object mutators.
 */
class BrokenArticle extends Model
{
    protected string $table = 'articles';

    protected array $casts = ['created_at' => 'datetime', 'views' => 'bool'];

    protected static function segregatedAccessorsMap(): array
    {
        return [
            'meta' => fn() => new \stdClass(),
        ];
    }

    protected static function segregatedMutatorsMap(): array
    {
        return [
            'title' => function ($value): void {
                $this->attributes['title'] = (object)['text' => $value];
            },
        ];
    }
}
