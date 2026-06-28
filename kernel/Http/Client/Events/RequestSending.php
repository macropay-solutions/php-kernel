<?php

namespace MacropaySolutions\Kernel\Http\Client\Events;

use MacropaySolutions\Kernel\Http\Client\Request;

class RequestSending
{
    /**
     * The request instance.
     *
     * @var \MacropaySolutions\Kernel\Http\Client\Request
     */
    public $request;

    /**
     * Create a new event instance.
     *
     * @param \MacropaySolutions\Kernel\Http\Client\Request $request
     * @return void
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }
}
