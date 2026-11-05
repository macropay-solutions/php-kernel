<?php

namespace MacropaySolutions\Kernel\Tests\Feature\Http;

use MacropaySolutions\Framework\Application;
use MacropaySolutions\Framework\Exceptions\Handler;
use MacropaySolutions\Kernel\Http\Request;
use MacropaySolutions\Kernel\Session\ArraySessionHandler;
use MacropaySolutions\Kernel\Session\Store;
use MacropaySolutions\Kernel\Support\ViewErrorBag;
use MacropaySolutions\Kernel\Translation\ArrayLoader;
use MacropaySolutions\Kernel\Translation\Translator;
use MacropaySolutions\Kernel\Validation\ValidationException;
use MacropaySolutions\Kernel\Validation\Validator;
use PHPUnit\Framework\TestCase;

/**
 * A browser request with a session is redirected with the errors flashed and without sensitive input.
 * Uses the real Application, because redirect() needs it.
 */
class ValidationRedirectTest extends TestCase
{
    protected function tearDown(): void
    {
        // Restore PHP's native handlers registered by the Application
        \restore_error_handler();
        \restore_exception_handler();

        parent::tearDown();
    }

    public function test_validation_failure_redirects_with_errors_and_without_sensitive_input(): void
    {
        $app = new Application();

        $session = new Store('test', new ArraySessionHandler(120));
        $session->start();

        $request = Request::create('http://localhost/form', 'POST', [
            'name' => 'Jon',
            'title' => '',
            'password' => 'secret',
            'current_password' => 'old-secret',
            'password_confirmation' => 'secret',
            'token' => 'abc',
        ]);
        $request->setFrameworkSession($session);
        $app->instance(Request::class, $request);

        $validator = new Validator(new Translator(new ArrayLoader(), 'en'), [], []);
        $validator->errors()->add('title', 'The title field is required.');
        $exception = (new ValidationException($validator))->redirectTo('http://localhost/form');

        $response = (new Handler())->render($request, $exception);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('http://localhost/form', $response->getTargetUrl());
        $this->assertInstanceOf(ViewErrorBag::class, $session->get('errors'));
        $this->assertSame(
            ['title' => ['The title field is required.']],
            $session->get('errors')->getBag('default')->messages()
        );
        $this->assertSame(['name' => 'Jon', 'title' => ''], $session->get('_old_input'));
    }
}
