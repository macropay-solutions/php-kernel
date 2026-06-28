<?php

namespace MacropaySolutions\Kernel\Session;

interface ExistenceAwareInterface
{
    /**
     * Set the existence state for the session.
     *
     * @param bool $value
     * @return \SessionHandlerInterface
     */
    public function setExists($value);
}
