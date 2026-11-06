<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Database;

use MacropaySolutions\Kernel\Database\Obvious\Model;
use MacropaySolutions\Kernel\Database\Obvious\Relations\HasMany;

class Author extends Model
{
    protected array $fillable = ['name', 'rating'];

    protected array $casts = ['rating' => 'int'];

    public function books(): HasMany
    {
        return $this->hasMany(Book::class, 'author_id', 'id');
    }
}
