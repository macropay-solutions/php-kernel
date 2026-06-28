<?php

namespace MacropaySolutions\Kernel\Http;

use ArrayAccess;
use Closure;
use MacropaySolutions\Kernel\Contracts\Support\Arrayable;
use MacropaySolutions\Kernel\Session\SymfonySessionDecorator;
use MacropaySolutions\Kernel\Support\Arr;
use MacropaySolutions\Kernel\Support\Str;
use MacropaySolutions\Kernel\Support\Traits\Macroable;
use RuntimeException;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpFoundation\Exception\SuspiciousOperationException;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class Request extends SymfonyRequest implements Arrayable, ArrayAccess
{
    use Concerns\CanBePrecognitive;
    use Concerns\InteractsWithContentTypes;
    use Concerns\InteractsWithFlashData;
    use Concerns\InteractsWithInput;
    use Macroable;

    /**
     * Override this into your \App\Request Class if needed
     */
    protected static bool $httpMethodParameterOverride = true;

    /**
     * All the converted files for the request.
     *
     * @var array
     */
    protected $convertedFiles;

    /**
     * The user resolver callback.
     *
     * @var \Closure
     */
    protected $userResolver;

    /**
     * The route resolver callback.
     *
     * @var \Closure
     */
    protected $routeResolver;

    /**
     * Create a new MacropaySolutions Kernel  HTTP request from server variables.
     *
     * @return static
     */
    public static function capture()
    {
        $content = null;
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (
            \str_contains($contentType, '/json')
            || \str_contains($contentType, '+json')
        ) {
            return new static(
                $_GET,
                (array)\json_decode($content = \file_get_contents('php://input'), true),
                [],
                $_COOKIE,
                $_FILES,
                $_SERVER,
                $content
            );
        }

        if (
            !\in_array(
                $_SERVER['REQUEST_METHOD'] ?? null,
                ['PUT', 'DELETE', 'PATCH', 'QUERY'],
                true
            )
        ) {
            return new static(
                $_GET,
                $_POST,
                [],
                $_COOKIE,
                $_FILES,
                $_SERVER,
                $content
            );
        }

        if (\PHP_VERSION_ID < 80400) {
            if ($contentType === '' || \str_starts_with($contentType, 'application/x-www-form-urlencoded')) {
                \parse_str($content = \file_get_contents('php://input'), $post);

                return new static(
                    $_GET,
                    $post,
                    [],
                    $_COOKIE,
                    $_FILES,
                    $_SERVER,
                    $content
                );
            }

            return new static(
                $_GET,
                $_POST,
                [],
                $_COOKIE,
                $_FILES,
                $_SERVER,
                $content
            );
        }

        try {
            [$post, $files] = \request_parse_body();
        } catch (\RequestParseBodyException) {
            $post = $_POST;
            $files = $_FILES;
        }

        return new static(
            $_GET,
            $post,
            [],
            $_COOKIE,
            $files,
            $_SERVER,
            $content
        );
    }


    /**
     * @inheritdoc
     */
    public function initialize(
        array $query = [],
        array $request = [],
        array $attributes = [],
        array $cookies = [],
        array $files = [],
        array $server = [],
        $content = null
    ): void {
        parent::initialize($query, $request, $attributes, $cookies, $files, $server, $content);

        if ($this->files->count() > 0) {
            $this->files->replace(static::cleanFiles($this->files->all()) ?? []);
        }
    }

    /**
     * Return the Request instance.
     *
     * @return $this
     */
    public function instance()
    {
        return $this;
    }

    /**
     * Get the request method.
     *
     * @return string
     */
    public function method()
    {
        return $this->getMethod();
    }

    /**
     * Get the root URL for the application.
     *
     * @return string
     */
    public function root()
    {
        return rtrim($this->getSchemeAndHttpHost() . $this->getBaseUrl(), '/');
    }

    /**
     * Get the URL (no query string) for the request.
     *
     * @return string
     */
    public function url()
    {
        return rtrim(preg_replace('/\?.*/', '', $this->getUri()), '/');
    }

    /**
     * Get the full URL for the request.
     *
     * @return string
     */
    public function fullUrl()
    {
        $query = $this->getQueryString();

        $question = $this->getBaseUrl() . $this->getPathInfo() === '/' ? '/?' : '?';

        return $query ? $this->url() . $question . $query : $this->url();
    }

    /**
     * Get the full URL for the request with the added query string parameters.
     *
     * @param array $query
     * @return string
     */
    public function fullUrlWithQuery(array $query)
    {
        $question = $this->getBaseUrl() . $this->getPathInfo() === '/' ? '/?' : '?';

        return count($this->query()) > 0
            ? $this->url() . $question . Arr::query(array_merge($this->query(), $query))
            : $this->fullUrl() . $question . Arr::query($query);
    }

    /**
     * Get the full URL for the request without the given query string parameters.
     *
     * @param array|string $keys
     * @return string
     */
    public function fullUrlWithoutQuery($keys)
    {
        $query = Arr::except($this->query(), $keys);

        $question = $this->getBaseUrl() . $this->getPathInfo() === '/' ? '/?' : '?';

        return count($query) > 0
            ? $this->url() . $question . Arr::query($query)
            : $this->url();
    }

    /**
     * Get the current path info for the request.
     *
     * @return string
     */
    public function path()
    {
        $pattern = trim($this->getPathInfo(), '/');

        return $pattern === '' ? '/' : $pattern;
    }

    /**
     * Get the current decoded path info for the request.
     *
     * @return string
     */
    public function decodedPath()
    {
        return rawurldecode($this->path());
    }

    /**
     * Get a segment from the URI (1 based index).
     *
     * @param int $index
     * @param string|null $default
     * @return string|null
     */
    public function segment($index, $default = null)
    {
        return Arr::get($this->segments(), $index - 1, $default);
    }

    /**
     * Get all the segments for the request path.
     *
     * @return array
     */
    public function segments()
    {
        $segments = explode('/', $this->decodedPath());

        return array_values(array_filter($segments, function ($value) {
            return $value !== '';
        }));
    }

    /**
     * Determine if the current request URI matches a pattern.
     *
     * @param mixed ...$patterns
     * @return bool
     */
    public function is(...$patterns)
    {
        $path = $this->decodedPath();

        return collect($patterns)->contains(fn($pattern) => Str::is($pattern, $path));
    }

    /**
     * Determine if the route name matches a given pattern.
     *
     * @param mixed ...$patterns
     * @return bool
     */
    public function routeIs(...$patterns)
    {
        return $this->route() && $this->route()->named(...$patterns);
    }

    /**
     * Determine if the current request URL and query string match a pattern.
     *
     * @param mixed ...$patterns
     * @return bool
     */
    public function fullUrlIs(...$patterns)
    {
        $url = $this->fullUrl();

        return collect($patterns)->contains(fn($pattern) => Str::is($pattern, $url));
    }

    /**
     * Get the host name.
     *
     * @return string
     */
    public function host()
    {
        return $this->getHost();
    }

    /**
     * Get the HTTP host being requested.
     *
     * @return string
     */
    public function httpHost()
    {
        return $this->getHttpHost();
    }

    /**
     * Get the scheme and HTTP host.
     *
     * @return string
     */
    public function schemeAndHttpHost()
    {
        return $this->getSchemeAndHttpHost();
    }

    /**
     * Determine if the request is the result of an AJAX call.
     *
     * @return bool
     */
    public function ajax()
    {
        return $this->isXmlHttpRequest();
    }

    /**
     * Determine if the request is the result of a PJAX call.
     *
     * @return bool
     */
    public function pjax()
    {
        return $this->headers->get('X-PJAX') == true;
    }

    /**
     * Determine if the request is the result of a prefetch call.
     *
     * @return bool
     */
    public function prefetch()
    {
        return strcasecmp($this->server->get('HTTP_X_MOZ') ?? '', 'prefetch') === 0 ||
            strcasecmp($this->headers->get('Purpose') ?? '', 'prefetch') === 0 ||
            strcasecmp($this->headers->get('Sec-Purpose') ?? '', 'prefetch') === 0;
    }

    /**
     * Determine if the request is over HTTPS.
     *
     * @return bool
     */
    public function secure()
    {
        return $this->isSecure();
    }

    /**
     * Get the client IP address.
     *
     * @return string|null
     */
    public function ip()
    {
        return $this->getClientIp();
    }

    /**
     * Get the client IP addresses.
     *
     * @return array
     */
    public function ips()
    {
        return $this->getClientIps();
    }

    /**
     * Get the client user agent.
     *
     * @return string|null
     */
    public function userAgent()
    {
        return $this->headers->get('User-Agent');
    }

    /**
     * Merge new input into the current request's input array.
     *
     * @param array $input
     * @return $this
     */
    public function merge(array $input)
    {
        $this->getInputSource()->add($input);

        return $this;
    }

    /**
     * Merge new input into the request's input, but only when that key is missing from the request.
     *
     * @param array $input
     * @return $this
     */
    public function mergeIfMissing(array $input)
    {
        return $this->merge(
            collect($input)->filter(function ($value, $key) {
                return $this->missing($key);
            })->toArray()
        );
    }

    /**
     * Replace the input for the current request.
     *
     * @param array $input
     * @return $this
     */
    public function replace(array $input)
    {
        $this->getInputSource()->replace($input);

        return $this;
    }

    /**
     * This method belongs to Symfony HttpFoundation and is not usually needed when using Kernel.
     *
     * Instead, you may use the "input" method.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if ($this !== $result = $this->attributes->get($key, $this)) {
            return $result;
        }

        if (\in_array($this->getRealMethod(), ['POST', 'PUT', 'PATCH', 'QUERY'], true)) {
            if ($this->request->has($key)) {
                return $this->request->all()[$key];
            }

            if ($this->query->has($key)) {
                return $this->query->all()[$key];
            }

            return $default;
        }

        if ($this->query->has($key)) {
            return $this->query->all()[$key];
        }

        if ($this->request->has($key)) {
            return $this->request->all()[$key];
        }

        return $default;
    }

    /**
     * Get the JSON payload for the request.
     *
     * @param string|null $key
     * @param mixed $default
     * @return \Symfony\Component\HttpFoundation\InputBag|mixed
     */
    public function json($key = null, $default = null)
    {
        if (null === $key) {
            return $this->request;
        }

        return \data_get($this->request->all(), $key, $default);
    }

    /**
     * @inheritdoc
     */
    public function getMethod(): string
    {
        if (null !== $this->method) {
            return $this->method;
        }

        $this->method = \strtoupper($this->server->get('REQUEST_METHOD', 'GET'));

        if ('POST' !== $this->method || !(static::$allowedHttpMethodOverride ?? true)) {
            return $this->method;
        }

        $method = $this->headers->get('X-HTTP-METHOD-OVERRIDE');

        if ((null === $method || '' === $method) && static::$httpMethodParameterOverride) {
            $method = $this->request->get('_method') ?? $this->query->get('_method', 'POST');
        }

        if (!\is_string($method) || '' === $method) {
            return $this->method;
        }

        $method = \strtoupper($method);

        if (static::$allowedHttpMethodOverride && !\in_array($method, static::$allowedHttpMethodOverride, true)) {
            return $this->method;
        }

        if (\strlen($method) !== strspn($method, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ')) {
            throw new SuspiciousOperationException('Invalid HTTP method override.');
        }

        return $this->method = $method;
    }

    /**
     * @inheritdoc
     * This is by default enabled in self::$httpMethodParameterOverride
     */
    public static function enableHttpMethodParameterOverride(): void
    {
        static::$httpMethodParameterOverride = true;
    }

    /**
     * @inheritdoc
     */
    public static function getHttpMethodParameterOverride(): bool
    {
        return static::$httpMethodParameterOverride;
    }

    /**
     * Get the input source for the request.
     *
     * @return \Symfony\Component\HttpFoundation\InputBag
     */
    protected function getInputSource()
    {
        return in_array($this->getRealMethod(), ['GET', 'HEAD']) ? $this->query : $this->request;
    }

    /**
     * Create a new request instance from the given Kernel request.
     *
     * @param \MacropaySolutions\Kernel\Http\Request $from
     * @param \MacropaySolutions\Kernel\Http\Request|null $to
     * @return static
     */
    public static function createFrom(self $from, $to = null)
    {
        $request = $to ?: new static();

        $request->initialize(
            $from->query->all(),
            $from->request->all(),
            $from->attributes->all(),
            $from->cookies->all(),
            $from->files->all(),
            $from->server->all(),
            $from->getContent()
        );

        $request->headers->replace($from->headers->all());

        $request->setRequestLocale($from->getLocale());

        $request->setDefaultRequestLocale($from->getDefaultLocale());

        if ($from->hasSession() && $session = $from->session()) {
            $request->setFrameworkSession($session);
        }

        $request->setUserResolver($from->getUserResolver());

        $request->setRouteResolver($from->getRouteResolver());

        return $request;
    }

    /**
     * Create an MacropaySolutions Kernel  request from a Symfony instance.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return static
     */
    public static function createFromBase(SymfonyRequest $request)
    {
        $newRequest = new static(
            $request->query->all(),
            $request->request->all(),
            $request->attributes->all(),
            $request->cookies->all(),
            $request->files->all(),
            $request->server->all(),
            $request->content
        );

        $newRequest->headers->replace($request->headers->all());

        return $newRequest;
    }

    /**
     * Filter the given array of files, removing any empty values.
     *
     * @param mixed $files
     * @return mixed
     */
    protected function filterFiles($files)
    {
        return static::cleanFiles($files);
    }

    /**
     * Filter the given array of files, removing any empty values.
     *
     * @param mixed $files
     */
    protected static function cleanFiles(mixed $files): ?array
    {
        if (!\is_array($files)) {
            return null;
        }

        foreach ($files as $key => $file) {
            if (\is_array($file)) {
                $files[$key] = static::cleanFiles($file);
            }

            if (empty($files[$key])) {
                unset($files[$key]);
            }
        }

        return $files;
    }

    /**
     * {@inheritdoc}
     */
    public function hasSession(bool $skipIfUninitialized = false): bool
    {
        return null !== $this->session;
    }

    /**
     * {@inheritdoc}
     */
    public function getSession(): SessionInterface
    {
        return $this->hasSession() ? $this->session : throw new SessionNotFoundException();
    }

    /**
     * Get the session associated with the request.
     *
     * @return \MacropaySolutions\Kernel\Contracts\Session\Session
     *
     * @throws \RuntimeException
     */
    public function session()
    {
        if (!$this->hasSession()) {
            throw new RuntimeException('Session store not set on request.');
        }

        return $this->session->store;
    }

    /**
     * Set the session instance on the request.
     *
     * @param \MacropaySolutions\Kernel\Contracts\Session\Session $session
     * @return void
     */
    public function setFrameworkSession($session)
    {
        $this->session = new SymfonySessionDecorator($session);
    }

    /**
     * Set the locale for the request instance.
     *
     * @param string $locale
     * @return void
     */
    public function setRequestLocale(string $locale)
    {
        $this->locale = $locale;
    }

    /**
     * Set the default locale for the request instance.
     *
     * @param string $locale
     * @return void
     */
    public function setDefaultRequestLocale(string $locale)
    {
        $this->defaultLocale = $locale;
    }

    /**
     * Get the user making the request.
     *
     * @param string|null $guard
     * @return mixed
     */
    public function user($guard = null)
    {
        return $this->getUserResolver()($guard);
    }

    /**
     * Get the route handling the request.
     *
     * @param string|null $param
     * @param mixed $default
     * @return \MacropaySolutions\Kernel\Routing\Route|object|string|null
     */
    public function route($param = null, $default = null)
    {
        $route = $this->getRouteResolver()();

        if (null === $route || null === $param) {
            return $route;
        }

        return $route->parameter($param, $default);
    }

    /**
     * Get a unique fingerprint for the request / route / IP address.
     *
     * @return string
     *
     * @throws \RuntimeException
     */
    public function fingerprint()
    {
        if (!$route = $this->route()) {
            throw new RuntimeException('Unable to generate fingerprint. Route unavailable.');
        }

        return sha1(
            implode(
                '|',
                array_merge(
                    $route->methods(),
                    [$route->getDomain(), $route->uri(), $this->ip()]
                )
            )
        );
    }

    /**
     * Set the JSON payload for the request.
     *
     * @param \Symfony\Component\HttpFoundation\InputBag $json
     * @return $this
     */
    public function setJson($json)
    {
        if ($this->isJson()) {
            $this->request->replace($json->all());
        }

        return $this;
    }

    /**
     * Get the user resolver callback.
     *
     * @return \Closure
     */
    public function getUserResolver()
    {
        return $this->userResolver ?: static fn() => null;
    }

    /**
     * Set the user resolver callback.
     *
     * @param \Closure $callback
     * @return $this
     */
    public function setUserResolver(Closure $callback)
    {
        $this->userResolver = $callback;

        return $this;
    }

    /**
     * Get the route resolver callback.
     *
     * @return \Closure
     */
    public function getRouteResolver()
    {
        return $this->routeResolver ?: static fn() => null;
    }

    /**
     * Set the route resolver callback.
     *
     * @param \Closure $callback
     * @return $this
     */
    public function setRouteResolver(Closure $callback)
    {
        $this->routeResolver = $callback;

        return $this;
    }

    /**
     * Get all the input and files for the request.
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->all();
    }

    /**
     * Determine if the given offset exists.
     *
     * @param string $offset
     * @return bool
     */
    public function offsetExists($offset): bool
    {
        $route = $this->route();

        return Arr::has(
            $this->all() + ($route ? $route->parameters() : []),
            $offset
        );
    }

    /**
     * Get the value at the given offset.
     *
     * @param string $offset
     * @return mixed
     */
    public function offsetGet($offset): mixed
    {
        return $this->__get($offset);
    }

    /**
     * Set the value at the given offset.
     *
     * @param string $offset
     * @param mixed $value
     * @return void
     */
    public function offsetSet($offset, $value): void
    {
        $this->getInputSource()->set($offset, $value);
    }

    /**
     * Remove the value at the given offset.
     *
     * @param string $offset
     * @return void
     */
    public function offsetUnset($offset): void
    {
        $this->getInputSource()->remove($offset);
    }

    /**
     * Check if an input element is set on the request.
     *
     * @param string $key
     * @return bool
     */
    public function __isset($key)
    {
        return null !== $this->__get($key);
    }

    /**
     * Get an input element from the request.
     *
     * @param string $key
     * @return mixed
     */
    public function __get($key)
    {
        return Arr::get($this->all(), $key, fn() => $this->route($key));
    }
}
