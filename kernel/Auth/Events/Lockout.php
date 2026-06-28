<?php

namespace MacropaySolutions\Kernel\Auth\Events;

use MacropaySolutions\Kernel\Http\Request;

class Lockout
{
    /**
     * The throttled request.
     *
     * @var \MacropaySolutions\Kernel\Http\Request
     */
    public $request;

    /**
     * Create a new event instance.
     *
     * @param \MacropaySolutions\Kernel\Http\Request $request
     * @return void
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }
}
