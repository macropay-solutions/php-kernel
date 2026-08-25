<?php

namespace MacropaySolutions\Kernel\Contracts\Validation;

interface UncompromisedVerifier
{
    /**
     * Verify that the given data has not been compromised in data leaks.
     */
    public function verify(array $data): bool;
}
