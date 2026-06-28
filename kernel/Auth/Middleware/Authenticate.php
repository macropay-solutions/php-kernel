<?php

namespace MacropaySolutions\Kernel\Auth\Middleware;

use Closure;
use MacropaySolutions\Kernel\Auth\AuthenticationException;
use MacropaySolutions\Kernel\Contracts\Auth\Factory as Auth;
use MacropaySolutions\Kernel\Contracts\Auth\Middleware\AuthenticatesRequests;
use MacropaySolutions\Kernel\Http\Request;

class Authenticate implements AuthenticatesRequests
{
    /**
     * The authentication factory instance.
     *
     * @var \MacropaySolutions\Kernel\Contracts\Auth\Factory
     */
    protected $auth;

    /**
     * Create a new middleware instance.
     *
     * @param \MacropaySolutions\Kernel\Contracts\Auth\Factory $auth
     * @return void
     */
    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
    }

    /**
     * Specify the guards for the middleware.
     *
     * @param string $guard
     * @param string $others
     * @return string
     */
    public static function using($guard, ...$others)
    {
        return static::class . ':' . implode(',', [$guard, ...$others]);
    }

    /**
     * Handle an incoming request.
     *
     * @param \MacropaySolutions\Kernel\Http\Request $request
     * @param \Closure $next
     * @param string[] ...$guards
     * @return mixed
     *
     * @throws \MacropaySolutions\Kernel\Auth\AuthenticationException
     */
    public function handle($request, Closure $next, ...$guards)
    {
        $this->authenticate($request, $guards);

        return $next($request);
    }

    /**
     * Determine if the user is logged in to any of the given guards.
     *
     * @param \MacropaySolutions\Kernel\Http\Request $request
     * @param array $guards
     * @return void
     *
     * @throws \MacropaySolutions\Kernel\Auth\AuthenticationException
     */
    protected function authenticate($request, array $guards)
    {
        if (empty($guards)) {
            $guards = [null];
        }

        foreach ($guards as $guard) {
            if ($this->auth->guard($guard)->check()) {
                $this->auth->shouldUse($guard);

                return;
            }
        }

        $this->unauthenticated($request, $guards);
    }

    /**
     * Handle an unauthenticated user.
     *
     * @param \MacropaySolutions\Kernel\Http\Request $request
     * @param array $guards
     * @return void
     *
     * @throws \MacropaySolutions\Kernel\Auth\AuthenticationException
     */
    protected function unauthenticated($request, array $guards)
    {
        throw new AuthenticationException(
            'Unauthenticated.',
            $guards,
            $this->redirectTo($request)
        );
    }

    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param \MacropaySolutions\Kernel\Http\Request $request
     * @return string|null
     */
    protected function redirectTo(Request $request)
    {
        //
    }
}
