<?php

namespace MacropaySolutions\Kernel\Contracts\Mail;

interface Factory
{
    /**
     * Get a mailer instance by name.
     *
     * @param string|null $name
     * @return \MacropaySolutions\Kernel\Contracts\Mail\Mailer
     */
    public function mailer($name = null);
}
