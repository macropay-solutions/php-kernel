<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\CachedApp;

use MacropaySolutions\Framework\Application;

class CachedApplication extends Application
{
    public function bootstrapRouter()
    {
        $this->router = new CachedRouter($this);
    }
}
