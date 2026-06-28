<?php

namespace MacropaySolutions\Kernel\Auth\Events;

use MacropaySolutions\Kernel\Queue\SerializesModels;

class Verified
{
    use SerializesModels;

    /**
     * The verified user.
     *
     * @var \MacropaySolutions\Kernel\Contracts\Auth\MustVerifyEmail
     */
    public $user;

    /**
     * Create a new event instance.
     *
     * @param \MacropaySolutions\Kernel\Contracts\Auth\MustVerifyEmail $user
     * @return void
     */
    public function __construct($user)
    {
        $this->user = $user;
    }
}
