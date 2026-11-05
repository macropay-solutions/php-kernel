<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Http;

use MacropaySolutions\Kernel\Http\FormRequest;

class TestFormRequest extends FormRequest
{
    public function redirectUrl(): string
    {
        return $this->getRedirectUrl();
    }
}
