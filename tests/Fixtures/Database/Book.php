<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Database;

use MacropaySolutions\Kernel\Database\Obvious\Model;
use MacropaySolutions\Kernel\Database\Obvious\Relations\BelongsTo;

class Book extends Model
{
    protected array $fillable = ['author_id', 'title', 'pages'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class, 'author_id', 'id', 'author');
    }
}
