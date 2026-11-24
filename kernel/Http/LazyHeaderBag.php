<?php

namespace MacropaySolutions\Kernel\Http;

use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\ServerBag;

/**
 * Reads single headers straight from the server variables; the full header list is built only when something
 * needs it. ServerBag::getHeaders() loops over every server variable, environment variables included.
 */
class LazyHeaderBag extends HeaderBag
{
    /**
     * ServerBag::getHeaders() also takes these from the server variables without the HTTP_ prefix, skipping ''.
     */
    protected const PLAIN_SERVER_HEADERS = [
        'content-type' => 'CONTENT_TYPE',
        'content-length' => 'CONTENT_LENGTH',
        'content-md5' => 'CONTENT_MD5',
    ];

    /**
     * The server variables as they were when the request was initialized, like the headers Symfony builds then.
     */
    protected ?array $server = null;

    public function __construct(array $server)
    {
        $this->server = $server;
    }

    public function all(?string $key = null): array
    {
        if (null !== $this->server && null !== $key && null !== ($values = $this->serverValues($key))) {
            return $values;
        }

        $this->buildHeaders();

        return parent::all($key);
    }

    public function has(string $key): bool
    {
        if (null !== $this->server && null !== ($values = $this->serverValues($key))) {
            return [] !== $values;
        }

        $this->buildHeaders();

        return parent::has($key);
    }

    public function replace(array $headers = []): void
    {
        // HeaderBag::replace() keeps the Cache-Control directives parsed from the replaced headers
        $this->buildHeaders();

        parent::replace($headers);
    }

    public function set(string $key, string|array|null $values, bool $replace = true): void
    {
        $this->buildHeaders();

        parent::set($key, $values, $replace);
    }

    public function remove(string $key): void
    {
        $this->buildHeaders();

        parent::remove($key);
    }

    public function addCacheControlDirective(string $key, bool|string $value = true): void
    {
        $this->buildHeaders();

        parent::addCacheControlDirective($key, $value);
    }

    public function hasCacheControlDirective(string $key): bool
    {
        $this->buildHeaders();

        return parent::hasCacheControlDirective($key);
    }

    public function getCacheControlDirective(string $key): bool|string|null
    {
        $this->buildHeaders();

        return parent::getCacheControlDirective($key);
    }

    public function removeCacheControlDirective(string $key): void
    {
        $this->buildHeaders();

        parent::removeCacheControlDirective($key);
    }

    public function getIterator(): \ArrayIterator
    {
        $this->buildHeaders();

        return parent::getIterator();
    }

    public function count(): int
    {
        $this->buildHeaders();

        return parent::count();
    }

    /**
     * The values HeaderBag would hold for the header, or null when only the full header list can tell.
     */
    protected function serverValues(string $key): ?array
    {
        $key = \strtr($key, self::UPPER, self::LOWER);

        if (\str_starts_with($key, 'php-auth-')) {
            // ServerBag::getHeaders() takes these from PHP_AUTH_* or decodes them from Authorization
            return null;
        }

        $serverKey = 'HTTP_' . \strtr($key, self::LOWER, self::UPPER);
        $prefixed = \array_key_exists($serverKey, $this->server);

        if (isset(static::PLAIN_SERVER_HEADERS[$key])) {
            $plainKey = static::PLAIN_SERVER_HEADERS[$key];

            if (\array_key_exists($plainKey, $this->server) && '' !== $this->server[$plainKey]) {
                // with both variables set, the order of the server variables decides
                return $prefixed ? null : $this->toValues($this->server[$plainKey]);
            }
        }

        if ($prefixed) {
            // a set HTTP_AUTHORIZATION is always the Authorization header; digest gets the full bag (see Request)
            return $this->toValues($this->server[$serverKey]);
        }

        // without HTTP_AUTHORIZATION, ServerBag::getHeaders() may derive it from other server variables
        return 'authorization' === $key ? null : [];
    }

    /**
     * HeaderBag::set() stores every value as a list; null stays null.
     */
    protected function toValues(mixed $value): array
    {
        return [null === $value ? null : (string)$value];
    }

    protected function buildHeaders(): void
    {
        if (null === $this->server) {
            return;
        }

        $headers = (new ServerBag($this->server))->getHeaders();
        // set() below calls buildHeaders() again
        $this->server = null;

        foreach ($headers as $key => $values) {
            parent::set($key, $values);
        }
    }
}
