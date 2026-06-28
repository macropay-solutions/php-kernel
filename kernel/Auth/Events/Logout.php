<?php

namespace MacropaySolutions\Kernel\Auth\Events;

use MacropaySolutions\Kernel\Queue\SerializesModels;

class Logout
{
    use SerializesModels;

    /**
     * The authentication guard name.
     *
     * @var string
     */
    public $guard;

    /**
     * The authenticated user.
     *
     * @var \MacropaySolutions\Kernel\Contracts\Auth\Authenticatable
     */
    public $user;

    /**
     * Create a new event instance.
     *
     * @param string $guard
     * @param \MacropaySolutions\Kernel\Contracts\Auth\Authenticatable $user
     * @return void
     */
    public function __construct($guard, $user)
    {
        $this->user = $user;
        $this->guard = $guard;
    }
}
