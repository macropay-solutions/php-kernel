<?php

namespace MacropaySolutions\Framework\Http;

use MacropaySolutions\Kernel\Support\Arr;
use MacropaySolutions\Kernel\Support\Str;
use RuntimeException;

/**
 * Route helpers for the dispatcher's array route: [status, action, parameters].
 */
trait RequestTrait
{
    /**
     * Determine if the route name matches a given pattern.
     *
     * @param mixed $patterns
     * @return bool
     */
    public function routeIs(...$patterns)
    {
        $name = $this->route()[1]['as'] ?? null;

        if (!\is_string($name)) {
            return false;
        }

        foreach ($patterns as $pattern) {
            if (Str::is($pattern, $name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the route handling the request, or one route parameter when $param is given.
     *
     * @param string|null $param
     * @param mixed $default
     * @return array|mixed|null
     */
    public function route($param = null, $default = null)
    {
        $route = ($this->getRouteResolver())();

        if (null === $param) {
            return $route;
        }

        return $this->filterDangerousWrapper(Arr::get($route[2] ?? [], $param, $default), $default);
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
        $route = $this->route();

        if (!\is_array($route) || [] === $route) {
            throw new RuntimeException('Unable to generate fingerprint. Route unavailable.');
        }

        return \sha1(\implode('|', [$this->getMethod(), $this->root(), $this->path(), $this->ip()]));
    }

    /**
     * Determine if the given offset exists.
     *
     * @param string $offset
     * @return bool
     */
    public function offsetExists($offset): bool
    {
        return Arr::has($this->all() + ($this->route()[2] ?? []), $offset);
    }
}
