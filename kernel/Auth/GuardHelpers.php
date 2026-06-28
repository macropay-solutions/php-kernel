<?php

namespace MacropaySolutions\Kernel\Auth;

use MacropaySolutions\Kernel\Contracts\Auth\Authenticatable as AuthenticatableContract;
use MacropaySolutions\Kernel\Contracts\Auth\UserProvider;

/**
 * These methods are typically the same across all guards.
 */
trait GuardHelpers
{
    /**
     * The currently authenticated user.
     *
     * @var \MacropaySolutions\Kernel\Contracts\Auth\Authenticatable|null
     */
    protected $user;

    /**
     * The user provider implementation.
     *
     * @var \MacropaySolutions\Kernel\Contracts\Auth\UserProvider
     */
    protected $provider;

    /**
     * Determine if the current user is authenticated. If not, throw an exception.
     *
     * @return \MacropaySolutions\Kernel\Contracts\Auth\Authenticatable
     *
     * @throws \MacropaySolutions\Kernel\Auth\AuthenticationException
     */
    public function authenticate()
    {
        return $this->user() ?? throw new AuthenticationException();
    }

    /**
     * Determine if the guard has a user instance.
     *
     * @return bool
     */
    public function hasUser()
    {
        return !is_null($this->user);
    }

    /**
     * Determine if the current user is authenticated.
     *
     * @return bool
     */
    public function check()
    {
        return !is_null($this->user());
    }

    /**
     * Determine if the current user is a guest.
     *
     * @return bool
     */
    public function guest()
    {
        return !$this->check();
    }

    /**
     * Get the ID for the currently authenticated user.
     *
     * @return int|string|null
     */
    public function id()
    {
        if ($this->user()) {
            return $this->user()->getAuthIdentifier();
        }
    }

    /**
     * Set the current user.
     *
     * @param \MacropaySolutions\Kernel\Contracts\Auth\Authenticatable $user
     * @return $this
     */
    public function setUser(AuthenticatableContract $user)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Forget the current user.
     *
     * @return $this
     */
    public function forgetUser()
    {
        $this->user = null;

        return $this;
    }

    /**
     * Get the user provider used by the guard.
     *
     * @return \MacropaySolutions\Kernel\Contracts\Auth\UserProvider
     */
    public function getProvider()
    {
        return $this->provider;
    }

    /**
     * Set the user provider used by the guard.
     *
     * @param \MacropaySolutions\Kernel\Contracts\Auth\UserProvider $provider
     * @return void
     */
    public function setProvider(UserProvider $provider)
    {
        $this->provider = $provider;
    }
}
