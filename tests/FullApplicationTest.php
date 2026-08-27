<?php

use MacropaySolutions\Kernel\Console\Command;
use MacropaySolutions\Kernel\Contracts\Debug\ExceptionHandler;
use MacropaySolutions\Kernel\Contracts\Validation\Factory;
use MacropaySolutions\Kernel\Http\Response;
use MacropaySolutions\Kernel\View\ViewServiceProvider;
use MacropaySolutions\Framework\Application;
use MacropaySolutions\Framework\Console\ConsoleServiceProvider;
use MacropaySolutions\Framework\Http\Request;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

class FullApplicationTest extends TestCase
{
    protected function setUp(): void
    {
    }

    protected function tearDown(): void
    {
        // Restore PHP's native handlers to prevent PHPUnit 11 "Risky" test warnings
        restore_error_handler();
        restore_exception_handler();

        m::close();

        parent::tearDown();
    }

    public function testBasicRequest()
    {
        $app = new Application();

        $app->router->get('/', 'FullApplicationTestController@hello');

        $response = $app->handle($request = Request::create('/', 'GET'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Hello World', $response->getContent());

        $this->assertInstanceOf(Request::class, $request);
    }

    public function testBasicSymfonyRequest()
    {
        $app = new Application();

        $app->router->get('/', 'FullApplicationTestController@hello');

        $response = $app->handle(Request::create('/', 'GET'));
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testAddRouteMultipleMethodRequest()
    {
        $app = new Application();

        $app->router->addRoute(['GET', 'POST'], '/', 'FullApplicationTestController@hello');

        $response = $app->handle(Request::create('/', 'GET'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Hello World', $response->getContent());

        $response = $app->handle(Request::create('/', 'POST'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Hello World', $response->getContent());
    }

    public function testRequestWithParameters()
    {
        $app = new Application();

        $app->router->get('/foo/{bar}/{baz}', 'FullApplicationTestController@params');

        $response = $app->handle($request = Request::create('/foo/1/2', 'GET'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('12', $response->getContent());

        $this->assertEquals(1, $request->route('bar'));
        $this->assertEquals(2, $request->route('baz'));
    }

    public function testCallbackRouteWithDefaultParameter()
    {
        $app = new Application();
        $app->router->get('/foo-bar/{baz}', 'FullApplicationTestController@defaultParam');

        $response = $app->handle(Request::create('/foo-bar/something', 'GET'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('something', $response->getContent());
    }

    public function testGlobalMiddleware()
    {
        $app = new Application();

        $app->middleware(['FrameworkTestMiddleware']);

        $app->router->get('/', 'FullApplicationTestController@hello');

        $response = $app->handle(Request::create('/', 'GET'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Middleware', $response->getContent());
    }

    public function testRouteMiddleware()
    {
        $app = new Application();

        $app->routeMiddleware(['foo' => 'FrameworkTestMiddleware', 'passing' => 'FrameworkTestPlainMiddleware']);

        $app->router->get('/', 'FullApplicationTestController@hello');

        $app->router->get('/foo', [
            'middleware' => 'foo',
            'uses' => 'FullApplicationTestController@hello',
        ]);

        $app->router->get('/bar', [
            'middleware' => ['foo'],
            'uses' => 'FullApplicationTestController@hello',
        ]);

        $app->router->get('/fooBar', [
            'middleware' => 'passing|foo',
            'uses' => 'FullApplicationTestController@hello',
        ]);

        $response = $app->handle(Request::create('/', 'GET'));
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Hello World', $response->getContent());

        $response = $app->handle(Request::create('/foo', 'GET'));
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Middleware', $response->getContent());

        $response = $app->handle(Request::create('/bar', 'GET'));
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Middleware', $response->getContent());

        $response = $app->handle(Request::create('/fooBar', 'GET'));
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Middleware', $response->getContent());
    }

    public function testGlobalMiddlewareParameters()
    {
        $app = new Application();

        $app->middleware(['FrameworkTestParameterizedMiddleware:foo,bar']);

        $app->router->get('/', 'FullApplicationTestController@hello');

        $response = $app->handle(Request::create('/', 'GET'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Middleware - foo - bar', $response->getContent());
    }

    public function testRouteMiddlewareParameters()
    {
        $app = new Application();

        $app->routeMiddleware(['foo' => 'FrameworkTestParameterizedMiddleware', 'passing' => 'FrameworkTestPlainMiddleware']);

        $app->router->get('/', [
            'middleware' => 'passing|foo:bar,boom',
            'uses' => 'FullApplicationTestController@hello',
        ]);

        $response = $app->handle(Request::create('/', 'GET'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Middleware - bar - boom', $response->getContent());
    }

    public function testWithMiddlewareDisabled()
    {
        $app = new Application();

        $app->middleware(['FrameworkTestMiddleware']);
        $app->instance('middleware.disable', true);

        $app->router->get('/', 'FullApplicationTestController@hello');

        $response = $app->handle(Request::create('/', 'GET'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Hello World', $response->getContent());
    }

    public function testTerminableGlobalMiddleware()
    {
        $app = new Application();

        $app->middleware(['FrameworkTestTerminateMiddleware']);

        $app->router->get('/', 'FullApplicationTestController@hello');

        $response = $app->handle(Request::create('/', 'GET'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('TERMINATED', $response->getContent());
    }

    public function testTerminateWithMiddlewareDisabled()
    {
        $app = new Application();

        $app->middleware(['FrameworkTestTerminateMiddleware']);
        $app->instance('middleware.disable', true);

        $app->router->get('/', 'FullApplicationTestController@hello');

        $response = $app->handle(Request::create('/', 'GET'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Hello World', $response->getContent());
    }

    public function testNotFoundResponse()
    {
        $app = new Application();
        $app->instance(ExceptionHandler::class, $mock = m::mock('MacropaySolutions\Framework\Exceptions\Handler[report]'));
        $mock->shouldIgnoreMissing();

        $app->router->get('/', 'FullApplicationTestController@hello');

        $response = $app->handle(Request::create('/foo', 'GET'));

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testMethodNotAllowedResponse()
    {
        $app = new Application();
        $app->instance(ExceptionHandler::class, $mock = m::mock('MacropaySolutions\Framework\Exceptions\Handler[report]'));
        $mock->shouldIgnoreMissing();

        $app->router->post('/', 'FullApplicationTestController@hello');

        $response = $app->handle(Request::create('/', 'GET'));

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testResponsableInterface()
    {
        $app = new Application();

        $app->router->get('/foo/{foo}', 'FullApplicationTestController@responsable');

        $request = Request::create('/foo/999', 'GET');
        $response = $app->handle($request);

        $this->assertEquals(999, $request->route('foo'));
        $this->assertEquals(999, $response->original);
    }

    public function testUncaughtExceptionResponse()
    {
        $app = new Application();
        $app->instance(ExceptionHandler::class, $mock = m::mock('MacropaySolutions\Framework\Exceptions\Handler[report]'));
        $mock->shouldIgnoreMissing();

        $app->router->get('/', 'FullApplicationTestController@exception');

        $response = $app->handle(Request::create('/', 'GET'));
        $this->assertInstanceOf(Response::class, $response);
    }

    public function testGeneratingUrls()
    {
        $app = new Application();
        $app->instance('request', Request::create('https://macropay-solutions.com', 'GET'));

        $app->router->get('/foo-bar', [
            'as' => 'foo',
            'uses' => 'FullApplicationTestController@empty',
        ]);

        $app->router->get('/foo-bar/{baz}/{boom}', [
            'as' => 'bar',
            'uses' => 'FullApplicationTestController@empty',
        ]);

        $app->router->get('/foo-bar/{baz}[/{boom}]', [
            'as' => 'optional',
            'uses' => 'FullApplicationTestController@empty',
        ]);

        $app->router->get('/foo-bar/{baz:[0-9]+}[/{boom}]', [
            'as' => 'regex',
            'uses' => 'FullApplicationTestController@empty',
        ]);

        $this->assertEquals('https://macropay-solutions.com/something', url('something'));
        $this->assertEquals('https://macropay-solutions.com/foo-bar', route('foo'));
        $this->assertEquals('https://macropay-solutions.com/foo-bar/1/2', route('bar', ['baz' => 1, 'boom' => 2]));
        $this->assertEquals(
            'https://macropay-solutions.com/foo-bar?baz=1&boom=2',
            route('foo', ['baz' => 1, 'boom' => 2])
        );
        $this->assertEquals(
            'https://macropay-solutions.com/foo-bar/1/2',
            route('optional', ['baz' => 1, 'boom' => 2])
        );
        $this->assertEquals('https://macropay-solutions.com/foo-bar/1', route('optional', ['baz' => 1]));
        $this->assertEquals('https://macropay-solutions.com/foo-bar/1/2', route('regex', ['baz' => 1, 'boom' => 2]));
        $this->assertEquals('https://macropay-solutions.com/foo-bar/1', route('regex', ['baz' => 1]));
    }

    public function testGeneratingUrlsForRegexParameters()
    {
        $app = new Application();
        $app->instance('request', Request::create('https://macropay-solutions.com', 'GET'));

        $app->router->get('/foo-bar', [
            'as' => 'foo',
            'uses' => 'FullApplicationTestController@empty',
        ]);

        $app->router->get('/foo-bar/{baz:[0-9]+}/{boom}', [
            'as' => 'bar',
            'uses' => 'FullApplicationTestController@empty',
        ]);

        $app->router->get('/foo-bar/{baz:[0-9]+}/{boom:[0-9]+}', [
            'as' => 'baz',
            'uses' => 'FullApplicationTestController@empty',
        ]);

        $app->router->get('/foo-bar/{baz:[0-9]{2,5}}', [
            'as' => 'boom',
            'uses' => 'FullApplicationTestController@empty',
        ]);

        $this->assertEquals('https://macropay-solutions.com/something', url('something'));
        $this->assertEquals('https://macropay-solutions.com/foo-bar', route('foo'));
        $this->assertEquals('https://macropay-solutions.com/foo-bar/1/2', route('bar', ['baz' => 1, 'boom' => 2]));
        $this->assertEquals('https://macropay-solutions.com/foo-bar/1/2', route('baz', ['baz' => 1, 'boom' => 2]));
        $this->assertEquals(
            'https://macropay-solutions.com/foo-bar/{baz:[0-9]+}/{boom:[0-9]+}?ba=1&bo=2',
            route('baz', ['ba' => 1, 'bo' => 2])
        );
        $this->assertEquals('https://macropay-solutions.com/foo-bar/5', route('boom', ['baz' => 5]));
    }

    public function testRegisterServiceProvider()
    {
        $app = new Application();
        $provider = new FrameworkTestServiceProvider($app);
        $app->register($provider);

        $this->assertTrue(true);
    }

    public function testApplicationBootsServiceProvidersOnBoot()
    {
        $app = new Application();

        $provider = new FrameworkBootableTestServiceProvider($app);
        $app->register($provider);

        $this->assertFalse($provider->booted);
        $app->boot();
        $this->assertTrue($provider->booted);
    }

    public function testRegisterServiceProviderAfterBoot()
    {
        $app = new Application();
        $provider = new FrameworkBootableTestServiceProvider($app);
        $app->boot();
        $app->register($provider);
        $this->assertTrue($provider->booted);
    }

    public function testApplicationBootsOnlyOnce()
    {
        $app = new Application();
        $provider = new class ($app) extends \MacropaySolutions\Kernel\Support\ServiceProvider {
            public $bootCount = 0;

            public function boot()
            {
                $this->bootCount += 1;
            }
        };

        $app->register($provider);
        $app->boot();
        $app->boot();
        $this->assertEquals(1, $provider->bootCount);
    }

    public function testApplicationBootsWhenRequestIsDispatched()
    {
        $app = new Application();
        $provider = new FrameworkBootableTestServiceProvider($app);
        $app->register($provider);
        $resp = $app->dispatch(Request::create('/'));
        $this->assertTrue($provider->booted);
    }

    public function testUsingCustomDispatcher()
    {
        $routes = new FastRoute\RouteCollector(
            new FastRoute\RouteParser\Std(),
            new FastRoute\DataGenerator\GroupCountBased()
        );

        $routes->addRoute('GET', '/', ['uses' => 'FullApplicationTestController@hello']);

        $app = new Application();

        $app->setDispatcher(new FastRoute\Dispatcher\GroupCountBased($routes->getData()));

        $response = $app->handle(Request::create('/', 'GET'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Hello World', $response->getContent());
    }

    public function testMiddlewareReceiveResponsesEvenWhenStringReturned()
    {
        unset($_SERVER['__middleware.response']);

        $app = new Application();

        $app->routeMiddleware(['foo' => 'FrameworkTestPlainMiddleware']);

        $app->router->get('/', [
            'middleware' => 'foo',
            'uses' => 'FullApplicationTestController@stringReturn',
        ]);

        $response = $app->handle(Request::create('/', 'GET'));
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Hello World', $response->getContent());
        $this->assertTrue($_SERVER['__middleware.response']);
    }

    public function testBasicControllerDispatching()
    {
        $app = new Application();

        $app->router->get('/show/{id}', 'FrameworkTestController@show');

        $response = $app->handle(Request::create('/show/25', 'GET'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('25', $response->getContent());
    }

    public function testBasicControllerDispatchingWithGroup()
    {
        $app = new Application();
        $app->routeMiddleware(['test' => FrameworkTestMiddleware::class]);

        $app->router->group(['middleware' => 'test'], function ($router) {
            $router->get('/show/{id}', 'FrameworkTestController@show');
        });

        $response = $app->handle(Request::create('/show/25', 'GET'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Middleware', $response->getContent());
    }

    public function testBasicControllerDispatchingWithGroupSuffix()
    {
        $app = new Application();
        $app->routeMiddleware(['test' => FrameworkTestMiddleware::class]);

        $app->router->group(['suffix' => '.{format:json|xml}'], function ($router) {
            $router->get('/show/{id}', 'FrameworkTestController@show');
        });

        $response = $app->handle(Request::create('/show/25.xml', 'GET'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('25', $response->getContent());
    }

    public function testBasicControllerDispatchingWithGroupAndSuffixWithPath()
    {
        $app = new Application();
        $app->routeMiddleware(['test' => FrameworkTestMiddleware::class]);

        $app->router->group(['suffix' => '/{format:json|xml}'], function ($router) {
            $router->get('/show/{id}', 'FrameworkTestController@show');
        });

        $response = $app->handle(Request::create('/show/test/json', 'GET'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('test', $response->getContent());
    }

    public function testBasicControllerDispatchingWithMiddlewareIntercept()
    {
        $app = new Application();
        $app->routeMiddleware(['test' => FrameworkTestMiddleware::class]);
        $app->router->get('/show/{id}', 'FrameworkTestControllerWithMiddleware@show');

        $response = $app->handle(Request::create('/show/25', 'GET'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Middleware', $response->getContent());
    }

    public function testBasicInvokableActionDispatching()
    {
        $app = new Application();

        $app->router->get('/action/{id}', 'FrameworkTestAction');

        $response = $app->handle(Request::create('/action/199', 'GET'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('199', $response->getContent());
    }

    public function testEnvironmentDetection()
    {
        $app = new Application();

        $this->assertEquals('production', $app->environment());
        $this->assertTrue($app->environment('production'));
        $this->assertTrue($app->environment(['production']));
    }

    public function testNamespaceDetection()
    {
        $app = new Application();
        $this->expectException('RuntimeException');
        $app->getNamespace();
    }

    public function testRunningUnitTestsDetection()
    {
        $app = new Application();

        $this->assertFalse($app->runningUnitTests());
    }

    public function testValidationHelpers()
    {
        $app = new Application();

        $app->router->get('/', 'FullApplicationTestController@validateRequest');

        $response = $app->handle(Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']));

        $this->assertEquals(422, $response->getStatusCode());

        $response = $app->handle(Request::create('/', 'GET', ['name' => 'Jon']));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($response->getContent(), '{"name":"Jon"}');
    }

    public function testRedirectResponse()
    {
        $app = new Application();

        $app->router->get('/', 'FullApplicationTestController@redirectHome');

        $response = $app->handle(Request::create('/', 'GET'));

        $this->assertEquals(302, $response->getStatusCode());
    }

    public function testRedirectToNamedRoute()
    {
        $app = new Application();

        $app->router->get('login', [
            'as' => 'login',
            'uses' => 'FullApplicationTestController@login',
        ]);

        $app->router->get('/', 'FullApplicationTestController@redirectLogin');

        $response = $app->handle(Request::create('/', 'GET'));

        $this->assertEquals(302, $response->getStatusCode());
    }

    public function testRequestUser()
    {
        $app = new Application();

        $app['auth']->viaRequest('api', function ($request) {
            return new \MacropaySolutions\Kernel\Auth\GenericUser(['id' => 1234]);
        });

        $app->router->get('/', 'FullApplicationTestController@authUser');

        $response = $app->handle(Request::create('/', 'GET'));

        $this->assertSame('1234', $response->getContent());
    }

    public function testCanResolveFilesystemFactoryFromContract()
    {
        $app = new Application();

        $filesystem = $app[MacropaySolutions\Kernel\Contracts\Filesystem\Factory::class];

        $this->assertInstanceOf(MacropaySolutions\Kernel\Contracts\Filesystem\Factory::class, $filesystem);
    }

    public function testCanResolveValidationFactoryFromContract()
    {
        $app = new Application();

        $validator = $app[Factory::class];

        $this->assertInstanceOf(Factory::class, $validator);
    }

    public function testNestedGroupMiddlewaresRequest()
    {
        $app = new Application();

        $app->router->group(['middleware' => 'middleware1'], function ($router) {
            $router->group(['middleware' => 'middleware2|middleware3'], function ($router) {
                $router->get('test', 'FrameworkTestController@show');
            });
        });

        $route = $app->router->getRoutes()['GET/test'];

        $this->assertEquals([
            'middleware1',
            'middleware2',
            'middleware3',
        ], $route['action']['middleware']);
    }

    public function testNestedGroupNamespaceRequest()
    {
        $app = new Application();

        $app->router->group(['namespace' => 'Hello'], function ($router) {
            $router->group(['namespace' => 'World'], function ($router) {
                $router->get('/world', 'Class@method');
            });
        });

        $routes = $app->router->getRoutes();

        $route = $routes['GET/world'];

        $this->assertEquals('Hello\\World\\Class@method', $route['action']['uses']);
    }

    public function testNestedGroupNamespaceWithFQCNClassName()
    {
        $app = new Application();

        $app->router->group(['namespace' => 'Hello'], function ($router) {
            $router->group(['namespace' => 'World'], function ($router) {
                $router->get('/world', '\Global\Namespaced\Class@method');
            });
        });

        $routes = $app->router->getRoutes();

        $route = $routes['GET/world'];

        $this->assertEquals('\\Global\\Namespaced\\Class@method', $route['action']['uses']);
    }

    public function testNestedGroupPrefixRequest()
    {
        $app = new Application();

        $app->router->group(['prefix' => 'hello'], function ($router) {
            $router->group(['prefix' => 'world'], function ($router) {
                $router->get('/world', 'Class@method');
            });
        });

        $routes = $app->router->getRoutes();

        $this->assertArrayHasKey('GET/hello/world/world', $routes);
    }

    public function testNestedGroupAsRequest()
    {
        $app = new Application();

        $app->router->group(['as' => 'hello'], function ($router) {
            $router->group(['as' => 'world'], function ($router) {
                $router->get('/world', 'Class@method');
            });
        });

        $this->assertArrayHasKey('hello.world', $app->router->namedRoutes);
        $this->assertEquals('/world', $app->router->namedRoutes['hello.world']);
    }

    public function testContainerBindingsAreNotOverwritten()
    {
        $app = new Application();

        $mock = m::mock(MacropaySolutions\Kernel\Bus\Dispatcher::class);

        $app->instance(MacropaySolutions\Kernel\Contracts\Bus\Dispatcher::class, $mock);

        $this->assertSame(
            $mock,
            $app->make(MacropaySolutions\Kernel\Contracts\Bus\Dispatcher::class)
        );
    }

    public function testApplicationClassCanBeOverwritten()
    {
        $app = new FrameworkTestApplication();

        $this->assertInstanceOf(FrameworkTestApplication::class, $app->make(Application::class));
    }

    public function testRequestIsReboundOnDispatch()
    {
        $app = new Application();
        $rebound = false;
        $app->rebinding('request', function () use (&$rebound) {
            $rebound = true;
        });

        $app->middleware([FrameworkTestDuplicateMiddleware::class]);

        $app->handle(Request::create('/'));
        $this->assertTrue($rebound);
    }

    public function testBatchesTableCommandIsRegistered()
    {
        $app = new FrameworkTestApplication();
        $app->register(ConsoleServiceProvider::class);
        $command = $app->make('command.queue.batches-table');
        $this->assertNotNull($command);
        $this->assertEquals('queue:batches-table', $command->getName());
    }

    public function testHandlingCommandsTerminatesApplication()
    {
        $app = new FrameworkTestApplication();
        $app->register(ConsoleServiceProvider::class);
        $app->register(ViewServiceProvider::class);

        $app->instance(ExceptionHandler::class, $mock = m::mock('MacropaySolutions\Framework\Exceptions\Handler[report]'));
        $mock->shouldIgnoreMissing();

        $kernel = $app[MacropaySolutions\Framework\Console\Kernel::class];

        (fn() => $kernel->getConsoleApp())->call($kernel)->resolveCommands(
            SendEmails::class,
        );

        $terminated = false;
        $app->terminating(function () use (&$terminated) {
            $terminated = true;
        });

        $input = new ArrayInput(['command' => 'send:emails']);

        $command = $kernel->handle($input, new NullOutput());

        $this->assertTrue($terminated);
    }

    public function testTerminationTests()
    {
        $app = new FrameworkTestApplication();

        $result = [];
        $callback1 = function () use (&$result) {
            $result[] = 1;
        };

        $callback2 = function () use (&$result) {
            $result[] = 2;
        };

        $callback3 = function () use (&$result) {
            $result[] = 3;
        };

        $app->terminating($callback1);
        $app->terminating($callback2);
        $app->terminating($callback3);

        $app->terminate();

        $this->assertEquals([1, 2, 3], $result);
    }
}

class FullApplicationTestController extends \MacropaySolutions\Framework\Routing\Controller
{
    public function hello() { return response('Hello World'); }
    public function params($bar, $baz) { return response($bar . $baz); }
    public function defaultParam($baz = 'default-value') { return response($baz); }
    public function responsable() { return new ResponsableResponse(); }
    public function exception() { throw new \RuntimeException('app exception'); }
    public function empty() {}
    public function stringReturn() { return 'Hello World'; }
    public function redirectHome() { return redirect('home'); }
    public function redirectLogin() { return redirect()->route('login'); }
    public function login() { return 'login'; }
    public function authUser(\MacropaySolutions\Kernel\Http\Request $request) { return $request->user()->getAuthIdentifier(); }

    public function validateRequest(\MacropaySolutions\Kernel\Http\Request $request)
    {
        return $this->validate($request, ['name' => 'required']);
    }
}

class FrameworkTestDuplicateMiddleware
{
    public function handle($request, $next)
    {
        return $next($request->duplicate());
    }
}

class FrameworkTestService
{
}

class FrameworkTestServiceProvider extends MacropaySolutions\Kernel\Support\ServiceProvider
{
    public function register()
    {
    }
}

class FrameworkBootableTestServiceProvider extends MacropaySolutions\Kernel\Support\ServiceProvider
{
    public $booted = false;

    public function boot()
    {
        $this->booted = true;
    }
}

class FrameworkTestController
{
    public function __construct(FrameworkTestService $service)
    {
        //
    }

    public function show($id)
    {
        return $id;
    }
}

class FrameworkTestControllerWithMiddleware extends MacropaySolutions\Framework\Routing\Controller
{
    public function __construct(FrameworkTestService $service)
    {
        $this->middleware('test');
    }

    public function show($id)
    {
        return $id;
    }
}

class FrameworkTestMiddleware
{
    public function handle($request, $next)
    {
        return response('Middleware');
    }
}

class FrameworkTestPlainMiddleware
{
    public function handle($request, $next)
    {
        $response = $next($request);
        $_SERVER['__middleware.response'] = $response instanceof Response;

        return $response;
    }
}

class FrameworkTestParameterizedMiddleware
{
    public function handle($request, $next, $parameter1, $parameter2)
    {
        return response("Middleware - $parameter1 - $parameter2");
    }
}

class FrameworkTestAction
{
    public function __invoke($id)
    {
        return $id;
    }
}

class FrameworkTestApplication extends Application
{
    public function version(): string
    {
        return 'Custom Framework App';
    }
}

class FrameworkTestTerminateMiddleware
{
    public function handle($request, $next)
    {
        return $next($request);
    }

    public function terminate($request, Response $response)
    {
        $response->setContent('TERMINATED');
    }
}

class ResponsableResponse implements \MacropaySolutions\Kernel\Contracts\Support\Responsable
{
    public function toResponse($request)
    {
        return $request->route('foo');
    }
}

class SendEmails extends Command
{
    protected $signature = 'send:emails';

    public function handle()
    {
        // ..
    }
}
