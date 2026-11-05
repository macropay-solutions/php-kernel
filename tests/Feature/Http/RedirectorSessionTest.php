<?php

namespace MacropaySolutions\Kernel\Tests\Feature\Http;

use MacropaySolutions\Kernel\Session\ArraySessionHandler;
use MacropaySolutions\Kernel\Session\Store;
use MacropaySolutions\Kernel\Support\ViewErrorBag;
use MacropaySolutions\Kernel\Tests\Fixtures\Http\TestRedirector;
use MacropaySolutions\Kernel\Tests\Fixtures\Http\TestRequest;
use MacropaySolutions\Kernel\Tests\TestCase;

class RedirectorSessionTest extends TestCase
{
    public function test_redirect_gets_the_started_session_and_can_flash(): void
    {
        $session = new Store('test', new ArraySessionHandler(120));
        $session->start();

        $request = new TestRequest();
        $request->testSession = $session;
        $this->container->instance('request', $request);

        $redirect = (new TestRedirector($this->container))->redirectTo('http://localhost/form');
        $redirect->withInput(['title' => 'draft'])->withErrors(['title' => ['The title field is required.']]);

        $this->assertSame($session, $redirect->getSession());
        $this->assertInstanceOf(ViewErrorBag::class, $session->get('errors'));
        $this->assertSame('draft', $session->getOldInput('title'));
    }

    public function test_redirect_without_a_session_has_none(): void
    {
        $this->container->instance('request', new TestRequest());

        $redirect = (new TestRedirector($this->container))->redirectTo('http://localhost/form');

        $this->assertNull($redirect->getSession());
        $this->assertSame('http://localhost/form', $redirect->getTargetUrl());
    }
}
