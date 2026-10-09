<?php

namespace MacropaySolutions\Kernel\Http\Middleware;

use Closure;
use Fruitcake\Cors\CorsService;
use MacropaySolutions\Kernel\Contracts\Container\Container;
use MacropaySolutions\Kernel\Http\Request;

/**
 * Use this as route middleware only
 * @see \MacropaySolutions\Framework\Exceptions\Handler::render for the preflight OPTIONS request handling
 */
class RouteAppendCorsHeaders
{
    /**
     * Create a new middleware instance.
     */
    public function __construct(
        protected Container $container
    ) {
    }

    /**
     * Handle the incoming request.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $response = $next($request);

        $origin = $request->headers->get('Origin');

        if ('' === (string)$origin) {
            return $response;
        }

        $service = $this->container->make(CorsService::class, [$this->container->make('config')->get('cors', [])]);

        return $service->addActualRequestHeaders(
            $request->getRealMethod() === 'OPTIONS' ?
                $service->varyHeader($response, 'Access-Control-Request-Method') :
                $response,
            $request
        );
    }
}
