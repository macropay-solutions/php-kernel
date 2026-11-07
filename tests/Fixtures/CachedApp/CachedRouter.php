<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\CachedApp;

use MacropaySolutions\Framework\Routing\Router;

class CachedRouter extends Router
{
    public function registerRoutes(): void
    {
        $this->get('/status', CachedController::class . '@status');
        $this->get('/items/{id:[0-9]+}', ['as' => 'items.show', 'uses' => CachedController::class . '@show']);
    }
}
