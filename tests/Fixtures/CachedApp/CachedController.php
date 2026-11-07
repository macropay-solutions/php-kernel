<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\CachedApp;

use MacropaySolutions\Framework\Routing\Controller;

class CachedController extends Controller
{
    public function status(): string
    {
        return (string)\config('probe.value');
    }

    public function show(string $id): string
    {
        return 'item:' . $id;
    }
}
