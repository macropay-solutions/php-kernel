<?php

namespace MacropaySolutions\Kernel\Http\Exceptions;

use RuntimeException;
use Throwable;

class HttpResponseException extends RuntimeException
{
    /**
     * The underlying response instance.
     *
     * @var \MacropaySolutions\Kernel\Http\Base\Response
     */
    protected $response;

    /**
     * Create a new HTTP response exception instance.
     *
     * @param \MacropaySolutions\Kernel\Http\Base\Response $response
     * @param \Throwable $previous
     * @return void
     */
    public function __construct(Response $response, ?Throwable $previous = null)
    {
        parent::__construct($previous?->getMessage() ?? '', $previous?->getCode() ?? 0, $previous);

        $this->response = $response;
    }

    /**
     * Get the underlying response instance.
     *
     * @return \MacropaySolutions\Kernel\Http\Base\Response
     */
    public function getResponse()
    {
        return $this->response;
    }
}
