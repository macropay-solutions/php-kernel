<?php

namespace MacropaySolutions\Kernel\Http\Client;

use GuzzleHttp\Psr7\Message;

class RequestException extends HttpClientException
{
    /**
     * The response instance.
     *
     * @var \MacropaySolutions\Kernel\Http\Client\Response
     */
    public $response;

    /**
     * Create a new exception instance.
     *
     * @param \MacropaySolutions\Kernel\Http\Client\Response $response
     * @return void
     */
    public function __construct(Response $response)
    {
        parent::__construct($this->prepareMessage($response), $response->status());

        $this->response = $response;
    }

    /**
     * Prepare the exception message.
     *
     * @param \MacropaySolutions\Kernel\Http\Client\Response $response
     * @return string
     */
    protected function prepareMessage(Response $response)
    {
        $message = "HTTP request returned status code {$response->status()}";

        $summary = Message::bodySummary($response->toPsrResponse());

        return is_null($summary) ? $message : $message .= ":\n{$summary}\n";
    }
}
