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

    /**
     * Dynamically call the default driver instance.
     */
    public function fwd(): \MacropaySolutions\Kernel\Contracts\Auth\PasswordBroker;
}
