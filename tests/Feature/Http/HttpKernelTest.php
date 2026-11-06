<?php

namespace MacropaySolutions\Kernel\Tests\Feature\Http;

use MacropaySolutions\Framework\Http\Request;
use MacropaySolutions\Kernel\Auth\Middleware\Authenticate;
use MacropaySolutions\Kernel\Auth\Middleware\Authorize;
use MacropaySolutions\Kernel\Contracts\Auth\Access\Gate;
use MacropaySolutions\Kernel\Http\Middleware\ConvertEmptyStringsToNull;
use MacropaySolutions\Kernel\Http\Middleware\TrimStrings;
use MacropaySolutions\Kernel\Tests\ApplicationTestCase;
use MacropaySolutions\Kernel\Tests\Fixtures\Http\HttpTestAuth;
use MacropaySolutions\Kernel\Tests\Fixtures\Http\HttpTestController;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requests through the real application: routing, middleware, authentication, authorization and errors.
 */
class HttpKernelTest extends ApplicationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->configure('auth');
        $this->app->make('auth')->viaRequest('api', [HttpTestAuth::class, 'userFromToken']);
        $this->app->make(Gate::class)->define('edit-post', [HttpTestAuth::class, 'canEditPost']);
        $this->app->routeMiddleware(['auth' => Authenticate::class, 'can' => Authorize::class]);

        $router = $this->app->router;
        $router->get('/me', ['middleware' => 'auth', 'uses' => HttpTestController::class . '@me']);
        $router->post('/echo', HttpTestController::class . '@echo');
        $router->get('/posts/{id:[0-9]+}', HttpTestController::class . '@show');
        $router->get('/forbidden', HttpTestController::class . '@forbidden');
        $router->get('/posts/{id}/edit', [
            'middleware' => ['auth', 'can:edit-post,id'],
            'uses' => HttpTestController::class . '@show',
        ]);
        $router->get('/edit-in-controller', [
            'middleware' => 'auth',
            'uses' => HttpTestController::class . '@authorizeInController',
        ]);
    }

    public function test_an_authenticated_request_reaches_the_controller(): void
    {
        $response = $this->send('GET', '/me', ['HTTP_AUTHORIZATION' => 'Bearer token-7']);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['id' => 7], \json_decode($response->getContent(), true));
    }

    public function test_a_request_without_a_valid_token_gets_401(): void
    {
        $this->assertSame(401, $this->send('GET', '/me')->getStatusCode());
        $this->assertSame(401, $this->send('GET', '/me', ['HTTP_AUTHORIZATION' => 'Bearer nope'])->getStatusCode());
    }

    public function test_the_can_middleware_and_controller_authorize_use_the_gate(): void
    {
        $owner = ['HTTP_AUTHORIZATION' => 'Bearer token-7'];
        $stranger = ['HTTP_AUTHORIZATION' => 'Bearer token-8'];

        $this->assertSame(200, $this->send('GET', '/posts/7/edit', $owner)->getStatusCode());
        $this->assertSame(403, $this->send('GET', '/posts/7/edit', $stranger)->getStatusCode());
        $this->assertSame('edited', $this->send('GET', '/edit-in-controller', $owner)->getContent());
        $this->assertSame(403, $this->send('GET', '/edit-in-controller', $stranger)->getStatusCode());
    }

    public function test_route_parameter_constraints(): void
    {
        $this->assertSame('{"id":"42"}', $this->send('GET', '/posts/42')->getContent());
        $this->assertSame(404, $this->send('GET', '/posts/abc')->getStatusCode());
    }

    /**
     * A wrong method on an existing route is answered like an unknown route (404), not 405.
     */
    public function test_abort_and_unknown_routes_and_methods_get_their_status_codes(): void
    {
        $this->assertSame(403, $this->send('GET', '/forbidden')->getStatusCode());
        $this->assertSame(404, $this->send('GET', '/missing')->getStatusCode());
        $this->assertSame(404, $this->send('DELETE', '/posts/1')->getStatusCode());
    }

    public function test_an_unauthenticated_json_request_gets_a_401_json_response(): void
    {
        $response = $this->send('GET', '/me', ['HTTP_ACCEPT' => 'application/json']);

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('Unauthenticated.', \json_decode($response->getContent(), true)['message']);
    }

    /**
     * Several requests through the same application, as the testing helpers send them.
     */
    public function test_each_request_is_authenticated_with_its_own_token(): void
    {
        $this->assertSame(401, $this->send('GET', '/me')->getStatusCode());
        $ana = $this->send('GET', '/me', ['HTTP_AUTHORIZATION' => 'Bearer token-7']);
        $ion = $this->send('GET', '/me', ['HTTP_AUTHORIZATION' => 'Bearer token-8']);

        $this->assertSame('{"id":7}', $ana->getContent());
        $this->assertSame('{"id":8}', $ion->getContent());
        $this->assertSame(401, $this->send('GET', '/me')->getStatusCode());
    }

    public function test_trim_strings_and_convert_empty_strings_middleware(): void
    {
        $this->app->middleware([TrimStrings::class, ConvertEmptyStringsToNull::class]);

        $input = ['name' => '  Ana  ', 'note' => '   ', 'nested' => ['x' => ' y ']];
        $response = $this->send('POST', '/echo', [], $input);

        $this->assertSame(
            ['name' => 'Ana', 'note' => null, 'nested' => ['x' => 'y']],
            \json_decode($response->getContent(), true)
        );
    }

    private function send(string $method, string $uri, array $server = [], array $parameters = []): Response
    {
        // Guards keep the request and the user they were resolved with; a new client starts with fresh guards
        $this->app->make('auth')->forgetGuards();

        return $this->app->handle(Request::create($uri, $method, $parameters, [], [], $server));
    }
}
