<?php

namespace MacropaySolutions\Kernel\Tests\Feature\Http;

use MacropaySolutions\Framework\Exceptions\Handler;
use MacropaySolutions\Kernel\Tests\Fixtures\Http\TestFormRequest;
use MacropaySolutions\Kernel\Tests\Fixtures\Http\TestRequest;
use MacropaySolutions\Kernel\Tests\TestCase;
use MacropaySolutions\Kernel\Translation\ArrayLoader;
use MacropaySolutions\Kernel\Translation\Translator;
use MacropaySolutions\Kernel\Validation\ValidationException;
use MacropaySolutions\Kernel\Validation\Validator;

/**
 * A ValidationException rendered by the exception handler returns the flat 422 error map instead of a 500.
 */
class ValidationResponseTest extends TestCase
{
    public function test_json_request_gets_the_flat_422_error_map(): void
    {
        $request = new TestRequest();
        $request->wantsJson = true;

        $response = (new Handler())->render($request, $this->validationException());

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(['title' => ['The title field is required.']], \json_decode($response->getContent(), true));
    }

    public function test_redirect_url_without_a_session_gets_the_json_error_map(): void
    {
        $exception = $this->validationException()->redirectTo('/form');

        $response = (new Handler())->render(new TestRequest(), $exception);

        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_form_request_without_redirect_has_no_redirect_url(): void
    {
        $this->assertSame('', (new TestFormRequest())->redirectUrl());
    }

    private function validationException(): ValidationException
    {
        $validator = new Validator(new Translator(new ArrayLoader(), 'en'), [], []);
        $validator->errors()->add('title', 'The title field is required.');

        return new ValidationException($validator);
    }
}
