<?php

use MacropaySolutions\Framework\Application;
use MacropaySolutions\Framework\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * route() encodes parameter values, so '/', '?', '#' or '..' cannot change the generated path,
 * and the router decodes them back to the original value.
 */
class RouteUrlEncodingTest extends TestCase
{
    protected function tearDown(): void
    {
        restore_error_handler();
        restore_exception_handler();

        parent::tearDown();
    }

    public static function routesProvider(): array
    {
        return [
            'simple route' => ['/users/{name}', 'https://macropay-solutions.com/users/a%2Fb%3Fx%3D1%23y'],
            'greedy route' => ['/docs/{name:.+}', 'https://macropay-solutions.com/docs/a%2Fb%3Fx%3D1%23y'],
        ];
    }

    #[DataProvider('routesProvider')]
    public function testValueIsEncodedAndDecodedBack(string $uri, string $expectedUrl): void
    {
        $app = $this->app($uri);

        $url = route('target', ['name' => 'a/b?x=1#y']);

        $this->assertSame($expectedUrl, $url);

        $response = $app->handle(Request::create($url, 'GET'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('a/b?x=1#y', $response->getContent());
    }

    public function testDotSegmentsStayInsideTheParameter(): void
    {
        $this->app('/users/{name}');

        $this->assertSame('https://macropay-solutions.com/users/..%2Fadmin', route('target', ['name' => '../admin']));
    }

    public function testExtraParametersStillGoToTheQueryString(): void
    {
        $this->app('/users/{name}');

        $this->assertSame(
            'https://macropay-solutions.com/users/J%C3%B6rg?tab=a+b',
            route('target', ['name' => 'Jörg', 'tab' => 'a b'])
        );
    }

    private function app(string $uri): Application
    {
        $app = new Application();
        $app->instance(
            \MacropaySolutions\Kernel\Http\Request::class,
            Request::create('https://macropay-solutions.com', 'GET')
        );
        $app->router->get($uri, ['as' => 'target', 'uses' => 'RouteUrlEncodingTestController@show']);

        return $app;
    }
}

class RouteUrlEncodingTestController
{
    public function show(string $name): string
    {
        return $name;
    }
}
