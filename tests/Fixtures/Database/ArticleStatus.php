<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Database;

enum ArticleStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
