<?php

namespace MacropaySolutions\Kernel\Contracts\Auth;

interface PasswordBrokerFactory
{
    /**
     * Get a password broker instance by name.
     *
     * @param string|null $name
     * @return \MacropaySolutions\Kernel\Contracts\Auth\PasswordBroker
     */
    public function broker($name = null);
}
