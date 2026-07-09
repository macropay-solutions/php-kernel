<?php

namespace MacropaySolutions\Framework\Concerns;

use Closure;
use FastRoute\Dispatcher;
use MacropaySolutions\Kernel\Contracts\Support\Responsable;
use MacropaySolutions\Kernel\Http\Exceptions\HttpResponseException;
use MacropaySolutions\Kernel\Http\Request;
use MacropaySolutions\Kernel\Http\Response;
use MacropaySolutions\Kernel\Support\Arr;
use MacropaySolutions\Framework\Http\Request as FrameworkRequest;
use MacropaySolutions\Framework\Routing\Controller as FrameworkController;
use MacropaySolutions\Framework\Routing\Pipeline;
use MacropaySolutions\Framework\Routing\Router;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use RuntimeException;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

trait RoutesRequests
{
    /**
     * List of all the global middleware for the application.
     * Note that this is executed before the route is found.
     * @var array
     */
    protected $middleware = [];

    /**
     * Global middlewares list that are applied ONLY after the route is found
     */
    protected array $foundRouteMiddleware = [];

    /**
     * All the route specific middleware short-hands.
     *
     * @var array
     */
    protected $routeMiddleware = [];

    /**
     * The current route being dispatched.
     *
     * @var array
     */
    protected $currentRoute;

    /**
     * The FastRoute dispatcher.
     *
     * @var \FastRoute\Dispatcher
     */
    protected $dispatcher;

    /**
     * Add new middleware to the application.
     *
     * @param \Closure|array $middleware
     * @return $this
     */
    public function middleware($middleware)
    {
        $this->middleware = \array_unique(\array_merge($this->middleware, (array)$middleware));

        return $this;
    }

    /**
     * Define the route middleware for the application.
     *
     * @param array $middleware
     * @return $this
     */
    public function routeMiddleware(array $middleware)
    {
        $this->routeMiddleware = \array_merge($this->routeMiddleware, $middleware);

        return $this;
    }

    /**
     * Dispatch request and return response.
     */
    public function handle(FrameworkRequest $request): SymfonyResponse
    {
        $response = $this->dispatch($request);

        if ([] !== $this->middleware) {
            $this->callTerminableMiddleware($response);
        }

        return $response;
    }

    /**
     * Run the application and send the response.
     */
    public function run(?FrameworkRequest $request = null): void
    {
        $response = $this->dispatch($request);

        if ($response instanceof SymfonyResponse) {
            $response->send();
        } else {
            echo (string)$response;
        }

        if ([] !== $this->middleware) {
            $this->callTerminableMiddleware($response);
        }

        $this->terminate();
    }

    /**
     * Call the terminable middleware.
     *
     * @param mixed $response
     * @return void
     */
    protected function callTerminableMiddleware($response)
    {
        if ($this->shouldSkipMiddleware()) {
            return;
        }

        foreach ($this->middleware as $middleware) {
            if (!\is_string($middleware)) {
                continue;
            }

            $instance = $this->make(\explode(':', $middleware)[0]);

            if (\method_exists($instance, 'terminate')) {
                $instance->terminate($this->instances[Request::class] ?? $this->resolve('request'), $response);
            }
        }
    }

    /**
     * Dispatch the incoming request.
     */
    public function dispatch(FrameworkRequest $request): SymfonyResponse
    {
        [$method, $pathInfo] = $this->parseIncomingRequest($request);

        try {
            $this->boot();

            return $this->prepareResponse($this->sendRequestThroughPipeline(
                $request,
                $this->middleware,
                function ($request) use ($method, $pathInfo) {
                    if ($request !== ($this->instances[Request::class] ?? $this->resolve('request'))) {
                        $this->instance(Request::class, $request);
                    }

                    if (null !== ($route = $this->router->getRoutes()[$uri = $method . $pathInfo] ?? null)) {
                        return $this->handleFoundRoute([true, $route['action'], []]);
                    }

                    $tmp = $this->router->getRoutesTree();
                    $word = \strtok($uri, '/');
                    $parameters = [];

                    do {
                        if (isset($tmp[$word])) {
                            $tmp = $tmp[$word];

                            continue;
                        }

                        if (isset($tmp['/'])) {
                            $tmp = $tmp['/'];
                            $parameters[] = $word;

                            continue;
                        }

                        break;
                    } while (false !== $word = \strtok('/'));

                    if ($word === false && isset($tmp['='])) {
                        foreach ($tmp['='] as $action) {
                            $combinedParams = \array_combine(\array_keys($action['p']), $parameters);

                            foreach (($action['p'] ?? []) as $param => $regex) {
                                if ($regex === Router::GENERAL_REGEX) {
                                    continue;
                                }

                                if (!\preg_match($regex, $combinedParams[$param])) {
                                    continue 2;
                                }
                            }

                            return $this->handleFoundRoute([
                                true,
                                $action,
                                $combinedParams
                            ]);
                        }
                    }

                    if (isset($this->dispatcher) || [] !== $this->router->getComplexRoutes()) {
                        return $this->handleDispatcherResponse(
                            $this->createDispatcher()->dispatch($method, $pathInfo)
                        );
                    }

                    return $this->sendExceptionToHandler(new NotFoundHttpException());
                }
            ));
        } catch (Throwable $e) {
            return $this->prepareResponse($this->sendExceptionToHandler($e));
        }
    }

    /**
     * Parse the incoming request and return the method and path info.
     */
    protected function parseIncomingRequest(FrameworkRequest $request): array
    {
        if ($this->bound('auth')) {
            $request->setUserResolver(function ($guard = null) {
                return $this->make('auth')->guard($guard)->user();
            });
        }

        $this->instance(Request::class, $request);

        return [$request->getMethod(), '/' . \trim($request->getPathInfo(), '/')];
    }

    /**
     * Create a FastRoute dispatcher instance for the application.
     *
     * @return \FastRoute\Dispatcher
     */
    protected function createDispatcher()
    {
        return $this->dispatcher ?: \FastRoute\simpleDispatcher(function ($r) {
            foreach ($this->router->getComplexRoutes() as $route) {
                $r->addRoute($route['method'], $route['uri'], $route['action']);
            }
        });
    }

    /**
     * Set the FastRoute dispatcher instance.
     *
     * @param \FastRoute\Dispatcher $dispatcher
     * @return void
     */
    public function setDispatcher(Dispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * Handle the response from the FastRoute dispatcher.
     *
     * @param array $routeInfo
     * @return mixed
     */
    protected function handleDispatcherResponse($routeInfo)
    {
        if ($routeInfo[0] === Dispatcher::FOUND) {
            return $this->handleFoundRoute($routeInfo);
        }

        throw new NotFoundHttpException();
    }

    /**
     * Handle a route found by the dispatcher.
     *
     * @param array $routeInfo
     * @return mixed
     * @throws NotFoundHttpException
     */
    protected function handleFoundRoute($routeInfo)
    {
        $this->currentRoute = $routeInfo;

        /** @var Request $request*/
        $request = $this->instances[Request::class] ?? $this->resolve('request');
        $action = $routeInfo[1];

        if (
            isset($action['domain'])
            && !\in_array(
                $request->getScheme() . '://' . \strtok($request->getHttpHost(), ':'),
                (array)$action['domain'],
                true
            )
        ) {
            throw new NotFoundHttpException();
        }

        $request->setRouteResolver(function () {
            return $this->currentRoute;
        });

        // Pipe through route middleware...
        if ([] !== $this->foundRouteMiddleware || isset($action['middleware'])) {
            return $this->sendThroughPipeline(
                \array_merge(
                    $this->foundRouteMiddleware,
                    isset($action['middleware']) ? $this->gatherMiddlewareClassNames($action['middleware']) : []
                ),
                fn(): mixed => $this->callActionOnArrayBasedRoute($routeInfo)
            );
        }

        return $this->callActionOnArrayBasedRoute($routeInfo);
    }

    /**
     * Call the Closure or invokable on the array based route.
     *
     * @param array $routeInfo
     * @return mixed
     */
    protected function callActionOnArrayBasedRoute($routeInfo)
    {
        $action = $routeInfo[1];

        if (isset($action['uses'])) {
            return $this->prePrepareResponse($this->callControllerAction($routeInfo));
        }

        foreach ($action as $value) {
            if ($value instanceof Closure) {
                throw new RuntimeException('Closures are not allowed.');
            }

            if (\is_object($value) && \is_callable($value)) {
                $callable = $value;
                break;
            }
        }

        if (!isset($callable)) {
            throw new RuntimeException('Unable to resolve route handler.');
        }

        try {
            return $this->prePrepareResponse($this->call($callable, $routeInfo[2]));
        } catch (HttpResponseException $e) {
            return $e->getResponse();
        }
    }

    /**
     * Call a controller based route.
     *
     * @param array $routeInfo
     * @return mixed
     */
    protected function callControllerAction($routeInfo)
    {
        $uses = $routeInfo[1]['uses'];

        if (\is_string($uses) && !\str_contains($uses, '@')) {
            $uses .= '@__invoke';
        }

        [$controller, $method] = \explode('@', $uses);

        if (!\method_exists($instance = $this->make($controller), $method)) {
            throw new NotFoundHttpException();
        }

        if ($instance instanceof FrameworkController) {
            return $this->callFrameworkController($instance, $method, $routeInfo);
        } else {
            return $this->callControllerCallable(
                [$instance, $method],
                $routeInfo[2]
            );
        }
    }

    /**
     * Send the request through a Framework controller.
     *
     * @param mixed $instance
     * @param string $method
     * @param array $routeInfo
     * @return mixed
     */
    protected function callFrameworkController($instance, $method, $routeInfo)
    {
        $middleware = $instance->getMiddlewareForMethod($method);

        if ([] !== $middleware) {
            return $this->callFrameworkControllerWithMiddleware(
                $instance,
                $method,
                $routeInfo,
                $middleware
            );
        }

        return $this->callControllerCallable(
            [$instance, $method],
            $routeInfo[2]
        );
    }

    /**
     * Send the request through a set of controller middleware.
     *
     * @param mixed $instance
     * @param string $method
     * @param array $routeInfo
     * @param array $middleware
     * @return mixed
     */
    protected function callFrameworkControllerWithMiddleware($instance, $method, $routeInfo, $middleware)
    {
        $middleware = $this->gatherMiddlewareClassNames($middleware);

        return $this->sendThroughPipeline($middleware, function () use ($instance, $method, $routeInfo) {
            return $this->callControllerCallable([$instance, $method], $routeInfo[2]);
        });
    }

    /**
     * Call a controller callable and return the response.
     *
     * @param callable $callable
     * @param array $parameters
     * @return \MacropaySolutions\Kernel\Http\Response|SymfonyResponse
     */
    protected function callControllerCallable(callable $callable, array $parameters = [])
    {
        try {
            return $this->prePrepareResponse($this->call($callable, $parameters));
        } catch (HttpResponseException $e) {
            return $e->getResponse();
        }
    }

    /**
     * Gather the full class names for the middleware short-cut string.
     *
     * @param string|array $middleware
     * @return array
     */
    protected function gatherMiddlewareClassNames($middleware)
    {
        $middleware = \is_string($middleware) ? \explode('|', $middleware) : (array)$middleware;

        return \array_map(function ($name) {
            [$name, $parameters] = \array_pad(\explode(':', $name, 2), 2, null);

            return Arr::get($this->routeMiddleware, $name, $name) . ($parameters ? ':' . $parameters : '');
        }, $middleware);
    }

    /**
     * Send the request through the pipeline with the given callback.
     *
     * @param array $middleware
     * @param \Closure $then
     * @return mixed
     */
    protected function sendThroughPipeline(array $middleware, Closure $then)
    {
        if ([] !== $middleware && !$this->shouldSkipMiddleware()) {
            return (new Pipeline($this))
                ->send($this->instances[Request::class] ?? $this->resolve('request'))
                ->through($middleware)
                ->then($then);
        }

        return $then();
    }

    /**
     * Send the request through the pipeline (used when the request is already in scope).
     */
    protected function sendRequestThroughPipeline(FrameworkRequest $request, array $middleware, Closure $then): mixed
    {
        if ([] !== $middleware && !$this->shouldSkipMiddleware()) {
            return (new Pipeline($this))->send($request)->through($middleware)->then($then);
        }

        return $then($request);
    }

    /**
     * Prepare the response for sending.
     *
     * @param mixed $response
     * @return \MacropaySolutions\Kernel\Http\Response|SymfonyResponse
     */
    public function prepareResponse($response)
    {
        $response = $this->prePrepareResponse($response);

        if ($response instanceof BinaryFileResponse) {
            return $response->prepare(Request::capture());
        }

        return $response->prepare($this->instances[Request::class] ?? $this->resolve('request'));
    }

    /**
     * Determines whether middleware should be skipped during request.
     */
    public function shouldSkipMiddleware(): bool
    {
        return static::$isDevEnv &&
            $this->bound('middleware.disable') &&
            $this->make('middleware.disable') === true;
    }

    protected function prePrepareResponse(mixed $response): SymfonyResponse
    {
        if ($response instanceof Responsable) {
            $response = $response->toResponse($this->instances[Request::class] ?? $this->resolve('request'));
        }

        if ($response instanceof PsrResponseInterface) {
            return  (new HttpFoundationFactory())->createResponse($response);
        }

        if (!$response instanceof SymfonyResponse) {
            return new Response($response);
        }

        return $response;
    }
}
