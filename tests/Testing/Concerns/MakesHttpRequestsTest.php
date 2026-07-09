<?php

use MacropaySolutions\Kernel\Http\JsonResponse;
use MacropaySolutions\Framework\Application;
use MacropaySolutions\Framework\Http\Request;
use MacropaySolutions\KernelDev\Framework\Testing\Concerns\MakesHttpRequests;
use PHPUnit\Framework\TestCase;

class MakesHttpRequestsTest extends TestCase
{
    use MakesHttpRequests;

    public function testReceiveJson()
    {
        $this->app = new Application();
        $this->app->router->get('/', 'MakesHttpRequestsTestController@jsonResponse');

        $this->handle(Request::create('/', 'GET'));

        // Test response is json
        $this->receiveJson();

        // Test response contains fragment
        $this->receiveJson(['foo' => 'bar']);
    }

    protected function tearDown(): void
    {
        restore_error_handler();
        restore_exception_handler();
        parent::tearDown();
    }
}

class MakesHttpRequestsTestController extends \MacropaySolutions\Framework\Routing\Controller
{
    public function jsonResponse()
    {
        return new JsonResponse(['foo' => 'bar', 'hello' => 'world']);
    }
}