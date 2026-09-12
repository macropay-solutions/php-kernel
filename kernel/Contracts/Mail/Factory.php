<?php

namespace MacropaySolutions\Kernel\Contracts\Mail;

use MacropaySolutions\Kernel\Mail\Mailer;

interface Factory
{
    /**
     * Get a mailer instance by name.
     *
     * @param string|null $name
     * @return \MacropaySolutions\Kernel\Contracts\Mail\Mailer
     */
    public function mailer($name = null);

    /**
     * Dynamically call the default driver instance.
     */
    public function fwd(): \MacropaySolutions\Kernel\Contracts\Mail\Mailer|Mailer;
}
