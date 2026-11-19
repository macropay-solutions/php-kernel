<?php

namespace RouteArrayCallableTestNamespace;

use MacropaySolutions\Framework\Application;
use MacropaySolutions\Framework\Http\Request;
use PHPUnit\Framework\TestCase;

/**
 * A route action may be [Controller::class, 'method'], also as 'uses' next to other attributes.
 * Before, such an array had no 'uses' and the request failed with "Unable to resolve route handler".
 */
class RouteArrayCallableTest extends TestCase
{
    protected function tearDown(): void
    {
        \restore_error_handler();
        \restore_exception_handler();

        parent::tearDown();
    }

    public function testArrayCallableAction(): void
    {
        $app = new Application();
        $app->router->get('/users/{id}', [RouteArrayCallableTestController::class, 'show']);

        $this->assertSame(RouteArrayCallableTestController::class . '@show', $this->uses($app, '/users/{id}'));
        $this->assertSame('user 5', $app->handle(Request::create('/users/5', 'GET'))->getContent());
    }

    public function testArrayCallableAsUsesKeepsTheOtherAttributes(): void
    {
        $app = new Application();
        $app->router->get(
            '/profile',
            ['as' => 'profile', 'uses' => [RouteArrayCallableTestController::class, 'profile']]
        );

        $this->assertSame(RouteArrayCallableTestController::class . '@profile', $this->uses($app, '/profile'));
        $this->assertStringEndsWith('/profile', \route('profile'));
        $this->assertSame('profile', $app->handle(Request::create('/profile', 'GET'))->getContent());
    }

    public function testTheGroupNamespaceIsNotPrependedToAClassName(): void
    {
        $app = new Application();
        $app->router->group(['namespace' => 'RouteArrayCallableTestNamespace'], static function ($router): void {
            $router->get('/a', [RouteArrayCallableTestController::class, 'profile']);
            $router->get('/b', 'RouteArrayCallableTestController@profile');
        });

        $this->assertSame(RouteArrayCallableTestController::class . '@profile', $this->uses($app, '/a'));
        $this->assertSame(RouteArrayCallableTestController::class . '@profile', $this->uses($app, '/b'));
        $this->assertSame('profile', $app->handle(Request::create('/a', 'GET'))->getContent());
    }

    public function testAnInvalidArrayIsRejectedWhenTheRouteIsAdded(): void
    {
        $app = new Application();

        foreach (
            [
                [RouteArrayCallableTestController::class, ''],
                [RouteArrayCallableTestController::class, 'a-b'],
                ['uses' => [RouteArrayCallableTestController::class]],
                ['uses' => ['c' => RouteArrayCallableTestController::class, 'm' => 'show']],
                ['uses' => ['', 'show']],
            ] as $action
        ) {
            try {
                $app->router->get('/x', $action);
                $this->fail('expected an InvalidArgumentException for ' . \json_encode($action));
            } catch (\InvalidArgumentException $e) {
                $this->assertSame('A route action array must be [Controller::class, \'method\'].', $e->getMessage());
            }
        }
    }

    private function uses(Application $app, string $uri): string
    {
        return $app->router->getAllRoutes()['GET' . $uri]['action']['uses'];
    }
}

class RouteArrayCallableTestController
{
    public function show(string $id): string
    {
        return 'user ' . $id;
    }

    public function profile(): string
    {
        return 'profile';
    }
}
