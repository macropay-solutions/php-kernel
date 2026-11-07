<?php

namespace MacropaySolutions\Kernel\Tests\Feature\Http;

use MacropaySolutions\Framework\Http\Request;
use MacropaySolutions\Kernel\Tests\ApplicationTestCase;
use MacropaySolutions\Kernel\Tests\Fixtures\Http\HttpTestController;
use Symfony\Component\HttpFoundation\Response;

/**
 * Routes take controller actions only: a closure action is refused when the route is hit and never runs.
 */
class RouteActionsTest extends ApplicationTestCase
{
    public static bool $closureRan = false;

    protected function setUp(): void
    {
        parent::setUp();

        self::$closureRan = false;
        $this->app->router->get('/closure', static function (): string {
            RouteActionsTest::$closureRan = true;

            return 'ran';
        });
        $this->app->router->get('/posts/{id:[0-9]+}', HttpTestController::class . '@show');
    }

    public function test_a_closure_route_is_refused_and_does_not_run(): void
    {
        $response = $this->send('GET', '/closure');

        $this->assertSame(500, $response->getStatusCode());
        $this->assertFalse(self::$closureRan);
        $this->assertStringNotContainsString('ran', (string)$response->getContent());
    }

    public function test_a_parameter_that_does_not_match_its_pattern_is_a_404_without_internals(): void
    {
        $response = $this->send('GET', '/posts/abc', ['HTTP_ACCEPT' => 'application/json']);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertStringNotContainsString('id', (string)$response->getContent());
        $this->assertStringNotContainsString('[0-9]', (string)$response->getContent());
    }

    private function send(string $method, string $uri, array $server = []): Response
    {
        return $this->app->handle(Request::create($uri, $method, [], [], [], $server));
    }
}
