<?php

namespace MacropaySolutions\Kernel\Auth\Middleware;

use Closure;
use MacropaySolutions\Kernel\Contracts\Auth\MustVerifyEmail;

class EnsureEmailIsVerified
{
    /**
     * Specify the redirect route for the middleware.
     *
     * @param string $route
     * @return string
     */
    public static function redirectTo($route)
    {
        return static::class . ':' . $route;
    }

    /**
     * Handle an incoming request.
     *
     * @param \MacropaySolutions\Kernel\Http\Request $request
     * @param \Closure $next
     * @param string|null $redirectToRoute
     * @return \MacropaySolutions\Kernel\Http\Response|\MacropaySolutions\Kernel\Http\RedirectResponse|null
     */
    public function handle($request, Closure $next, $redirectToRoute = null)
    {
        if (
            !$request->user() ||
            ($request->user() instanceof MustVerifyEmail &&
                !$request->user()->hasVerifiedEmail())
        ) {
            if ($request->expectsJson()) {
                abort(403, 'Your email address is not verified.');

                return null;
            }

            return \redirect()->guest(\url()->route($redirectToRoute ?: 'verification.notice'));
        }

        return $next($request);
    }
}
