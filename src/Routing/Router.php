<?php

namespace MacropaySolutions\Framework\Routing;

use FastRoute\RouteParser;
use FastRoute\RouteParser\Std;
use Illuminate\Support\Arr;

class Router
{
    public const GENERAL_REGEX = '~^[^/]+$~';

    /**
     * The application instance.
     *
     * @var \MacropaySolutions\Framework\Application
     */
    public $app;

    /**
     * The route group attribute stack.
     *
     * @var array
     */
    protected $groupStack = [];

    /**
     * All the routes waiting to be registered.
     *
     * @var array
     */
    protected $routes = [];

    protected array $routesTree = [];

    protected array $complexRoutes = [];

    /**
     * All the named routes and full URL pairs.
     *
     * @var array
     */
    public $namedRoutes = [];

    /**
     * app.url config cache
     */
    protected ?string $appUrl = null;

    protected ?bool $isDebug = null;
    protected ?RouteParser $parser = null;

    /**
     * Router constructor.
     *
     * @param \MacropaySolutions\Framework\Application $app
     */
    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * Register a set of routes with a set of shared attributes.
     *
     * @param array $attributes
     * @param \Closure $callback
     * @return void
     */
    public function group(array $attributes, \Closure $callback)
    {
        if (isset($attributes['middleware']) && is_string($attributes['middleware'])) {
            $attributes['middleware'] = explode('|', $attributes['middleware']);
        }

        $this->updateGroupStack($attributes);

        $callback($this);

        array_pop($this->groupStack);
    }

    /**
     * Update the group stack with the given attributes.
     *
     * @param array $attributes
     * @return void
     */
    protected function updateGroupStack(array $attributes)
    {
        if (!empty($this->groupStack)) {
            $attributes = $this->mergeWithLastGroup($attributes);
        }

        $this->groupStack[] = $attributes;
    }

    /**
     * Merge the given group attributes.
     *
     * @param array $new
     * @param array $old
     * @return array
     */
    public function mergeGroup($new, $old)
    {
        $new['namespace'] = static::formatUsesPrefix($new, $old);

        $new['prefix'] = static::formatGroupPrefix($new, $old);

        if (isset($old['domain'])) {
            $new['domain'] = !isset($new['domain']) ?
                $old['domain'] :
                \array_values(\array_unique(\array_merge((array)$old['domain'], (array)$new['domain'])));
        }

        if (isset($old['as'])) {
            $new['as'] = $old['as'] . (isset($new['as']) ? '.' . $new['as'] : '');
        }

        if (isset($old['suffix']) && !isset($new['suffix'])) {
            $new['suffix'] = $old['suffix'];
        }

        return \array_merge_recursive(Arr::except($old, ['namespace', 'prefix', 'as', 'suffix', 'domain']), $new);
    }

    /**
     * Merge the given group attributes with the last added group.
     *
     * @param array $new
     * @return array
     */
    protected function mergeWithLastGroup($new)
    {
        return $this->mergeGroup($new, end($this->groupStack));
    }

    /**
     * Format the uses prefix for the new group attributes.
     *
     * @param array $new
     * @param array $old
     * @return string|null
     */
    protected static function formatUsesPrefix($new, $old)
    {
        if (isset($new['namespace'])) {
            return isset($old['namespace']) && strpos($new['namespace'], '\\') !== 0
                ? trim($old['namespace'], '\\') . '\\' . trim($new['namespace'], '\\')
                : trim($new['namespace'], '\\');
        }

        return $old['namespace'] ?? null;
    }

    /**
     * Format the prefix for the new group attributes.
     *
     * @param array $new
     * @param array $old
     * @return string|null
     */
    protected static function formatGroupPrefix($new, $old)
    {
        $oldPrefix = $old['prefix'] ?? null;

        if (isset($new['prefix'])) {
            return trim($oldPrefix ?? '', '/') . '/' . trim($new['prefix'], '/');
        }

        return $oldPrefix;
    }

    /**
     * Add a route to the collection.
     *
     * @param array|string $method
     * @param string $uri
     * @param mixed $action
     * @return void
     */
    public function addRoute($method, $uri, $action)
    {
        $action = $this->parseAction($action);

        $attributes = null;

        if ($this->hasGroupStack()) {
            $attributes = $this->mergeWithLastGroup([]);
        }

        if (isset($attributes) && is_array($attributes)) {
            if (isset($attributes['prefix'])) {
                $uri = trim($attributes['prefix'], '/') . '/' . trim($uri, '/');
            }

            if (isset($attributes['suffix'])) {
                $uri = trim($uri, '/') . rtrim($attributes['suffix'], '/');
            }

            $action = $this->mergeGroupAttributes($action, $attributes);
        }

        $uri = '/' . trim($uri, '/');

        if (isset($action['as'])) {
            $parts = \parse_url(\last((array)($action['domain'] ??
                ($this->appUrl ??= $this->app->make('config')->get('app.url')))));

            if (
                isset($this->namedRoutes[$action['as']])
                && ($this->isDebug ??= (bool)$this->app->make('config')->get('app.debug'))
            ) {
                $this->app->make('log')->debug('Duplicate route alias detected: ' . $action['as']);
            }

            $this->namedRoutes[$action['as']] = (
                isset($parts['host']) && isset($parts['scheme']) ? $parts['scheme'] . '://' . $parts['host'] : ''
            ) . $uri;
        }

        if (is_array($method)) {
            foreach ($method as $verb) {
                $this->addRouteToCollections($verb, $uri, $action);
            }
            return;
        }

        $this->addRouteToCollections($method, $uri, $action);
    }

    public function getRoutesTree(): array
    {
        return $this->routesTree;
    }

    public function getComplexRoutes(): array
    {
        return $this->complexRoutes;
    }

    public function match(array $verbList, string $uri, mixed $action): static
    {
        $this->addRoute(\array_map('strtoupper', $verbList), $uri, $action);

        return $this;
    }

    protected function addRouteToCollections(string $method, string $uri, array $action): void
    {
        $routePayload = ['method' => $method, 'uri' => $uri, 'action' => $action];

        foreach (($this->parser ??= new Std())->parse($uri) as $parsedRoute) {
            /**
             *  For $uri string '/fixedRoutePart/{varName}[/moreFixed/{varName2:\d+}]', if {varName} is interpreted as
             *  a placeholder and [...] is interpreted as an optional route part, the expected $parsedRoute is:
             *
             *  [
             *      // first route: without optional part
             *      [
             *          '/fixedRoutePart/',
             *          ['varName', '[^/]+'],
             *      ],
             *      // second route: with optional part
             *      [
             *          '/fixedRoutePart/',
             *          ['varName', '[^/]+'],
             *          '/moreFixed/',
             *          ['varName2', '[0-9]+'],
             *      ],
             *  ]
             */
            foreach ($parsedRoute as $i => $segment) {
                if (\is_array($segment)) {
                    $regex = \end($segment);

                    if (
                        // Detect Mid-Route Greedy Wildcards
                        \str_contains($regex, '.+')
                        || \str_contains($regex, '.*')
                        // Detect Inline/Adjacent Parameters (Previous segment MUST be a string ending in '/')
                        || (
                            ($prev = $parsedRoute[$i - 1] ?? null) !== null
                            && (!\is_string($prev) || !\str_ends_with($prev, '/'))
                        )
                        // Detect Inline/Adjacent Parameters (Next segment MUST be a string starting with '/')
                        || (
                            ($next = $parsedRoute[$i + 1] ?? null) !== null
                            && (!\is_string($next) || !\str_starts_with($next, '/'))
                        )
                    ) {
                        $this->complexRoutes[$method . $uri] = $routePayload;

                        continue 2;
                    }
                }
            }

            $node = &$this->routesTree;
            $parameters = [];
            $staticPath = [];
            $isDynamic = false;

            foreach ([$method, ...$parsedRoute] as $i => $segment) {
                if (\is_string($segment)) {
                    $key = \trim($segment, '/');

                    if ($key === '') {
                        continue;
                    }

                    foreach (\explode('/', $key) as $k) {
                        if (!isset($node[$k])) {
                            $node[$k] = [];
                        }

                        $node = &$node[$k];

                        if (!$isDynamic && $i > 0) {
                            $staticPath[] = $k;
                        }
                    }

                    continue;
                }

                if (\is_array($segment)) {
                    $isDynamic = true;
                    $key = '/';
                    $paramName = \reset($segment);
                    $regex = \next($segment);

                    $parameters[$paramName] = '~^' . $regex . '$~';

                    if (!isset($node[$key])) {
                        $node[$key] = [];
                    }

                    $node = &$node[$key];
                }
            }

            if (!isset($node['='])) {
                $node['='] = [];
            }

            $action['p'] = $parameters;
            $node['='][] = $action;

            if (!$isDynamic) {
                $normalizedStaticUri = '/' . \implode('/', $staticPath);
                $this->routes[$method . $normalizedStaticUri] = [
                    'method' => $method,
                    'uri'    => $normalizedStaticUri,
                    'action' => $action
                ];
            }
        }
    }

    /**
     * Parse the action into an array format.
     *
     * @param mixed $action
     * @return array
     */
    protected function parseAction($action)
    {
        if (is_string($action)) {
            return ['uses' => $action];
        } elseif (!is_array($action)) {
            return [$action];
        }

        if (isset($action['middleware']) && is_string($action['middleware'])) {
            $action['middleware'] = explode('|', $action['middleware']);
        }

        return $action;
    }

    /**
     * Determine if the router currently has a group stack.
     *
     * @return bool
     */
    public function hasGroupStack()
    {
        return !empty($this->groupStack);
    }

    /**
     * Merge the group attributes into the action.
     *
     * @param array $action
     * @param array $attributes
     * @return array
     */
    protected function mergeGroupAttributes(array $action, array $attributes)
    {
        return $this->mergeNamespaceGroup(
            $this->mergeMiddlewareGroup(
                $this->mergeAsGroup(
                    $this->mergeDomainGroup($action, $attributes['domain'] ?? null),
                    $attributes['as'] ?? null
                ),
                $attributes['middleware'] ?? null
            ),
            $attributes['namespace'] ?? null
        );
    }

    protected function mergeDomainGroup(array $action, string|array|null $domain): array
    {
        if (isset($domain)) {
            $action['domain'] = !isset($action['domain'])
                ? $domain
                : \array_values(\array_unique(\array_merge((array)$domain, (array)$action['domain'])));
        }

        return $action;
    }

    /**
     * Merge the namespace group into the action.
     *
     * @param array $action
     * @param string $namespace
     * @return array
     */
    protected function mergeNamespaceGroup(array $action, $namespace = null)
    {
        if (isset($namespace, $action['uses'])) {
            $action['uses'] = $this->prependGroupNamespace($action['uses'], $namespace);
        }

        return $action;
    }

    /**
     * Prepend the namespace onto the use clause.
     *
     * @param string $class
     * @param string $namespace
     * @return string
     */
    protected function prependGroupNamespace($class, $namespace = null)
    {
        return $namespace !== null && strpos($class, '\\') !== 0
            ? $namespace . '\\' . $class : $class;
    }

    /**
     * Merge the middleware group into the action.
     *
     * @param array $action
     * @param array $middleware
     * @return array
     */
    protected function mergeMiddlewareGroup(array $action, $middleware = null)
    {
        if (isset($middleware)) {
            if (isset($action['middleware'])) {
                $action['middleware'] = array_merge($middleware, $action['middleware']);
            } else {
                $action['middleware'] = $middleware;
            }
        }

        return $action;
    }

    /**
     * Merge the as group into the action.
     *
     * @param array $action
     * @param string $as
     * @return array
     */
    protected function mergeAsGroup(array $action, $as = null)
    {
        if (isset($as) && !empty($as)) {
            if (isset($action['as'])) {
                $action['as'] = $as . '.' . $action['as'];
            } else {
                $action['as'] = $as;
            }
        }

        return $action;
    }

    /**
     * Register a route with the application.
     *
     * @param string $uri
     * @param mixed $action
     * @return $this
     */
    public function head($uri, $action)
    {
        $this->addRoute('HEAD', $uri, $action);

        return $this;
    }

    /**
     * Register a route with the application.
     *
     * @param string $uri
     * @param mixed $action
     * @return $this
     */
    public function query(string $uri, mixed $action): static
    {
        $this->addRoute('QUERY', $uri, $action);

        return $this;
    }

    /**
     * Register a route with the application.
     *
     * @param string $uri
     * @param mixed $action
     * @return $this
     */
    public function get($uri, $action)
    {
        $this->addRoute('GET', $uri, $action);

        return $this;
    }

    /**
     * Register a route with the application.
     *
     * @param string $uri
     * @param mixed $action
     * @return $this
     */
    public function post($uri, $action)
    {
        $this->addRoute('POST', $uri, $action);

        return $this;
    }

    /**
     * Register a route with the application.
     *
     * @param string $uri
     * @param mixed $action
     * @return $this
     */
    public function put($uri, $action)
    {
        $this->addRoute('PUT', $uri, $action);

        return $this;
    }

    /**
     * Register a route with the application.
     *
     * @param string $uri
     * @param mixed $action
     * @return $this
     */
    public function patch($uri, $action)
    {
        $this->addRoute('PATCH', $uri, $action);

        return $this;
    }

    /**
     * Register a route with the application.
     *
     * @param string $uri
     * @param mixed $action
     * @return $this
     */
    public function delete($uri, $action)
    {
        $this->addRoute('DELETE', $uri, $action);

        return $this;
    }

    /**
     * Register a route with the application.
     *
     * @param string $uri
     * @param mixed $action
     * @return $this
     */
    public function options($uri, $action)
    {
        $this->addRoute('OPTIONS', $uri, $action);

        return $this;
    }

    /**
     * Get the raw routes for the application.
     *
     * @return array
     */
    public function getRoutes()
    {
        return $this->routes;
    }

    /**
     * This might be slow on many routes so use it only on deploy scenario
     */
    public function getAllRoutes(): array
    {
        return \array_merge($this->reconstructRoutesFromTree($this->routesTree), $this->routes, $this->complexRoutes);
    }

    /**
     * This might be slow on many routes so use it only on deploy scenario
     */
    protected function reconstructRoutesFromTree(array $tree, string $method = '', array $segments = []): array
    {
        $routes = [];

        foreach ($tree as $key => $node) {
            if ($key === '=') {
                foreach ($node as $action) {
                    $paramNames = \array_keys($action['p'] ?? []);
                    $paramIndex = 0;
                    $reconstructedSegments = [];

                    foreach ($segments as $segment) {
                        if ($segment === '/') {
                            $paramName = $paramNames[$paramIndex++] ?? 'param';
                            $regex = $action['p'][$paramName] ?? '';

                            if ($regex !== '' && $regex !== self::GENERAL_REGEX) {
                                if (\str_starts_with($regex, '~^') && \str_ends_with($regex, '$~')) {
                                    $regex = \substr($regex, 2, -2);
                                }

                                $reconstructedSegments[] = '{' . $paramName . ':' . $regex . '}';

                                continue;
                            }

                            $reconstructedSegments[] = '{' . $paramName . '}';

                            continue;
                        }

                        $reconstructedSegments[] = $segment;
                    }

                    $uri = '/' . \implode('/', $reconstructedSegments);

                    $routes[$method . $uri] ??= ['method' => $method, 'uri' => $uri, 'action' => $action];
                }

                continue;
            }

            if (\is_array($node)) {
                $routes = $method === '' ?
                    \array_merge($routes, $this->reconstructRoutesFromTree($node, $key, $segments)) :
                    \array_merge($routes, $this->reconstructRoutesFromTree($node, $method, [...$segments, $key]));
            }
        }

        return $routes;
    }
}
