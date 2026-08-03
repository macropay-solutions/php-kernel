<?php

namespace MacropaySolutions\Kernel\Http\Middleware;

use Closure;
use MacropaySolutions\Kernel\Http\Base\BinaryFileResponse;
use MacropaySolutions\Kernel\Http\Base\StreamedResponse;
use MacropaySolutions\Kernel\Support\Carbon;
use MacropaySolutions\Kernel\Support\Str;

class SetCacheHeaders
{
    /**
     * Specify the options for the middleware.
     *
     * @param array|string $options
     * @return string
     */
    public static function using($options)
    {
        if (is_string($options)) {
            return static::class . ':' . $options;
        }

        return collect($options)
            ->map(fn($value, $key) => is_int($key) ? $value : "{$key}={$value}")
            ->map(fn($value) => Str::finish($value, ';'))
            ->pipe(fn($options) => rtrim(static::class . ':' . $options->implode(''), ';'));
    }

    /**
     * Add cache related HTTP headers.
     *
     * @param \MacropaySolutions\Kernel\Http\Request $request
     * @param \Closure $next
     * @param string|array $options
     * @return \MacropaySolutions\Kernel\Http\Base\Response
     *
     * @throws \InvalidArgumentException
     */
    public function handle($request, Closure $next, $options = [])
    {
        $response = $next($request);

        if (
            !$request->isMethodCacheable()
            || (
                !$response->getContent()
                && !$response instanceof BinaryFileResponse
                && !$response instanceof StreamedResponse
            )
        ) {
            return $response;
        }

        if (is_string($options)) {
            $options = $this->parseOptions($options);
        }

        if (isset($options['etag']) && $options['etag'] === true) {
            $options['etag'] = $response->getEtag() ?? \hash('md5', $response->getContent());
        }

        if (isset($options['last_modified'])) {
            if (is_numeric($options['last_modified'])) {
                $options['last_modified'] = Carbon::createFromTimestamp($options['last_modified']);
            } else {
                $options['last_modified'] = Carbon::parse($options['last_modified']);
            }
        }

        $response->setCache($options);
        $response->isNotModified($request);

        return $response;
    }

    /**
     * Parse the given header options.
     *
     * @param string $options
     * @return array
     */
    protected function parseOptions($options)
    {
        return collect(explode(';', rtrim($options, ';')))->mapWithKeys(function ($option) {
            $data = explode('=', $option, 2);

            return [$data[0] => $data[1] ?? true];
        })->all();
    }
}
