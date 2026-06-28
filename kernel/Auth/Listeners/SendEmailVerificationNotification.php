<?php

namespace MacropaySolutions\Kernel\Auth\Listeners;

use MacropaySolutions\Kernel\Auth\Events\Registered;
use MacropaySolutions\Kernel\Contracts\Auth\MustVerifyEmail;

class SendEmailVerificationNotification
{
    /**
     * Handle the event.
     *
     * @param \MacropaySolutions\Kernel\Auth\Events\Registered $event
     * @return void
     */
    public function handle(Registered $event)
    {
        if ($event->user instanceof MustVerifyEmail && !$event->user->hasVerifiedEmail()) {
            $event->user->sendEmailVerificationNotification();
        }
    }
}
