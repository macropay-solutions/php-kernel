<?php

namespace MacropaySolutions\Kernel\Contracts\Support;

interface MessageProvider
{
    /**
     * Get the messages for the instance.
     *
     * @return \MacropaySolutions\Kernel\Contracts\Support\MessageBag
     */
    public function getMessageBag();
}
