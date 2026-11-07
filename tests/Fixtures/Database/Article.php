<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Database;

use MacropaySolutions\Kernel\Database\Obvious\Builder;
use MacropaySolutions\Kernel\Database\Obvious\Model;
use MacropaySolutions\Kernel\Database\Obvious\Relations\BelongsTo;
use MacropaySolutions\Kernel\Database\Obvious\SoftDeletes;

class Article extends Model
{
    use SoftDeletes;

    protected array $fillable = ['author_id', 'title', 'views', 'status'];

    protected array $casts = ['views' => 'int', 'status' => ArticleStatus::class, 'author_id' => 'string'];

    public static function segregatedScopesMap(): array
    {
        return [
            'popular' => static function (Builder $builder): Builder {
                return $builder->where('views', '>=', 100);
            },
            'minViews' => static function (Builder $builder, int $min): Builder {
                return $builder->where('views', '>=', $min);
            },
        ];
    }

    protected static function segregatedAccessorsMap(): array
    {
        return [
            'headline' => fn() => \strtoupper((string)$this->getAttributeValue('title')),
        ];
    }

    protected static function segregatedMutatorsMap(): array
    {
        return [
            'title' => function ($value): void {
                $this->attributes['title'] = \trim((string)$value);
            },
        ];
    }

    public function writtenBy(): BelongsTo
    {
        return $this->belongsTo(Author::class, 'author_id', 'id', 'writtenBy');
    }
}
