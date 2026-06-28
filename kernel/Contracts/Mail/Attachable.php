<?php

namespace MacropaySolutions\Kernel\Contracts\Mail;

interface Attachable
{
    /**
     * Get an attachment instance for this entity.
     *
     * @return \MacropaySolutions\Kernel\Mail\Attachment
     */
    public function toMailAttachment();
}
