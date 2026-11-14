<?php

use MacropaySolutions\Framework\Application;
use MacropaySolutions\Framework\Http\Request;
use PHPUnit\Framework\TestCase;

/**
 * Route parameters are URL-decoded for every route: the routes tree decoded them, while complex routes
 * (resolved by FastRoute on the raw path) passed them still encoded, also past the dangerous wrapper filter.
 */
class RouteParameterDecodingTest extends TestCase
{
    protected function tearDown(): void
    {
        restore_error_handler();
        restore_exception_handler();

        parent::tearDown();
    }

    public function testSimpleRouteParameterIsDecoded(): void
    {
        $app = new Application();
        $app->router->get('/users/{name}', 'RouteParameterDecodingTestController@show');

        $response = $app->handle(Request::create('/users/Jos%C3%A9', 'GET'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('José', $response->getContent());
    }

    public function testInlineParametersOfComplexRouteAreDecoded(): void
    {
        $app = new Application();
        $app->router->get('/files/{name}.{ext}', 'RouteParameterDecodingTestController@file');

        $response = $app->handle(Request::create('/files/a%20b.txt', 'GET'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('a b|txt', $response->getContent());
    }

    public function testGreedyParameterOfComplexRouteIsDecoded(): void
    {
        $app = new Application();
        $app->router->get('/docs/{name:.+}', 'RouteParameterDecodingTestController@show');

        $response = $app->handle(Request::create('/docs/a%20b/c%2Fd', 'GET'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('a b/c/d', $response->getContent());
    }

    public function testEncodedPhpWrapperInComplexRouteIsRejected(): void
    {
        $app = new Application();
        $app->router->get('/docs/{name:.+}', 'RouteParameterDecodingTestController@show');

        $response = $app->handle(Request::create('/docs/php%3A%2F%2Ffilter%2Fresource%3Dx', 'GET'));

        $this->assertSame(404, $response->getStatusCode());
    }
}

class RouteParameterDecodingTestController
{
    public function show(string $name): string
    {
        return $name;
    }

    public function file(string $name, string $ext): string
    {
        return $name . '|' . $ext;
    }
}
