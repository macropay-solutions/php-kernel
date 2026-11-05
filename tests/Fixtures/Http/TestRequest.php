<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Http;

use MacropaySolutions\Kernel\Http\Request;
use MacropaySolutions\Kernel\Session\Store;

class TestRequest extends Request
{
    public bool $wantsJson = false;

    public ?Store $testSession = null;

    public function expectsJson()
    {
        return $this->wantsJson;
    }

    public function hasSession(bool $skipIfUninitialized = false): bool
    {
        return null !== $this->testSession;
    }

    public function session()
    {
        return $this->testSession;
    }
}
