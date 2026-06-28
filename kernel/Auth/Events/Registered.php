<?php

namespace MacropaySolutions\Kernel\Auth\Events;

use MacropaySolutions\Kernel\Queue\SerializesModels;

class Registered
{
    use SerializesModels;

    /**
     * The authenticated user.
     *
     * @var \MacropaySolutions\Kernel\Contracts\Auth\Authenticatable
     */
    public $user;

    /**
     * Create a new event instance.
     *
     * @param \MacropaySolutions\Kernel\Contracts\Auth\Authenticatable $user
     * @return void
     */
    public function __construct($user)
    {
        $this->user = $user;
    }
}
