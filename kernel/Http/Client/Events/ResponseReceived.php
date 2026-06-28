<?php

namespace MacropaySolutions\Kernel\Http\Client\Events;

use MacropaySolutions\Kernel\Http\Client\Request;
use MacropaySolutions\Kernel\Http\Client\Response;

class ResponseReceived
{
    /**
     * The request instance.
     *
     * @var \MacropaySolutions\Kernel\Http\Client\Request
     */
    public $request;

    /**
     * The response instance.
     *
     * @var \MacropaySolutions\Kernel\Http\Client\Response
     */
    public $response;

    /**
     * Create a new event instance.
     *
     * @param \MacropaySolutions\Kernel\Http\Client\Request $request
     * @param \MacropaySolutions\Kernel\Http\Client\Response $response
     * @return void
     */
    public function __construct(Request $request, Response $response)
    {
        $this->request = $request;
        $this->response = $response;
    }
}
