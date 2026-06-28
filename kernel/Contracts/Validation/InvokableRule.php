<?php

namespace MacropaySolutions\Kernel\Contracts\Validation;

use Closure;

/**
 * @see ValidationRule
 */
interface InvokableRule
{
    /**
     * Run the validation rule.
     *
     * @param string $attribute
     * @param mixed $value
     * @param \Closure(string): \MacropaySolutions\Kernel\Translation\PotentiallyTranslatedString $fail
     * @return void
     */
    public function __invoke(string $attribute, mixed $value, Closure $fail);
}
