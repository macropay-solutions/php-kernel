<?php

namespace MacropaySolutions\Kernel\Session\Middleware;

use Closure;
use MacropaySolutions\Kernel\Auth\AuthenticationException;
use MacropaySolutions\Kernel\Contracts\Auth\Factory as AuthFactory;
use MacropaySolutions\Kernel\Contracts\Session\Middleware\AuthenticatesSessions;
use MacropaySolutions\Kernel\Http\Request;

class AuthenticateSession implements AuthenticatesSessions
{
    /**
     * The authentication factory implementation.
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
    public function __construct(AuthFactory $auth)
    {
        $this->auth = $auth;
    }

    /**
     * Handle an incoming request.
     *
     * @param \MacropaySolutions\Kernel\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (!$request->hasSession() || !$request->user()) {
            return $next($request);
        }

        if (!$request->session()->has('password_hash_' . $this->auth->getDefaultDriver())) {
            $this->storePasswordHashInSession($request);
        }

        $stored = $request->session()->get('password_hash_' . $this->auth->getDefaultDriver());
        $password = $request->user()->getAuthPassword();

        // sessions stored before 1.6.39 hold the raw hash; it is replaced after this request
        if ($stored !== $this->passwordHashForSession($password) && $stored !== $password) {
            $this->logout($request);
        }

        return tap($next($request), function () use ($request) {
            if (!is_null($this->guard()->user())) {
                $this->storePasswordHashInSession($request);
            }
        });
    }

    /**
     * Store the user's current password hash in the session.
     *
     * @param \MacropaySolutions\Kernel\Http\Request $request
     * @return void
     */
    protected function storePasswordHashInSession($request)
    {
        if (!$request->user()) {
            return;
        }

        $request->session()->put([
            'password_hash_' . $this->auth->getDefaultDriver() => $this->passwordHashForSession(
                $request->user()->getAuthPassword()
            ),
        ]);
    }

    /**
     * The session keeps a MAC, not the password hash: with the cookie driver the session goes to the browser.
     */
    protected function passwordHashForSession(?string $passwordHash): string
    {
        return \hash_hmac('sha256', (string)$passwordHash, (string)\app('config')->get('app.key'));
    }

    /**
     * Log the user out of the application.
     *
     * @param \MacropaySolutions\Kernel\Http\Request $request
     * @return void
     *
     * @throws \MacropaySolutions\Kernel\Auth\AuthenticationException
     */
    protected function logout($request)
    {
        $this->guard()->logoutCurrentDevice();

        $request->session()->flush();

        throw new AuthenticationException(
            'Unauthenticated.',
            [$this->auth->getDefaultDriver()],
            $this->redirectTo($request)
        );
    }

    /**
     * Get the guard instance that should be used by the middleware.
     *
     * @return \MacropaySolutions\Kernel\Contracts\Auth\Factory|\MacropaySolutions\Kernel\Contracts\Auth\Guard
     */
    protected function guard()
    {
        return $this->auth;
    }

    /**
     * Get the path the user should be redirected to when their session is not authenticated.
     *
     * @param \MacropaySolutions\Kernel\Http\Request $request
     * @return string|null
     */
    protected function redirectTo(Request $request)
    {
        //
    }
}
