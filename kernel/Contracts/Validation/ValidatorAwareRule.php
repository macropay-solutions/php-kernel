<?php

namespace MacropaySolutions\Kernel\Contracts\Validation;

use MacropaySolutions\Kernel\Validation\Validator;

interface ValidatorAwareRule
{
    /**
     * Set the current validator.
     *
     * @param \MacropaySolutions\Kernel\Validation\Validator $validator
     * @return $this
     */
    public function setValidator(Validator $validator);
}
