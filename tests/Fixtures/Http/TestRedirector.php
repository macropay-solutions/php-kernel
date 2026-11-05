<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Http;

use MacropaySolutions\Framework\Http\Redirector;
use MacropaySolutions\Kernel\Container\Container;
use MacropaySolutions\Kernel\Http\RedirectResponse;

/**
 * Runs the real Redirector::createRedirect() on a plain container instead of the full application.
 */
class TestRedirector extends Redirector
{
    public function __construct(Container $container)
    {
        $this->app = $container;
    }

    public function redirectTo(string $path): RedirectResponse
    {
        return $this->createRedirect($path, 302, []);
    }
}
