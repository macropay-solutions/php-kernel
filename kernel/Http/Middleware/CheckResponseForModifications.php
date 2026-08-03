<?php

namespace MacropaySolutions\Kernel\Http\Middleware;

use Closure;
use MacropaySolutions\Kernel\Http\Base\Response;

class CheckResponseForModifications
{
    /**
     * Handle an incoming request.
     *
     * @param \MacropaySolutions\Kernel\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        if ($response instanceof Response) {
            $response->isNotModified($request);
        }

        return $response;
    }
}
