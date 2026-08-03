<?php

use MacropaySolutions\Framework\Bus\PendingDispatch;
use MacropaySolutions\Kernel\Container\Container;
use MacropaySolutions\Kernel\Contracts\Auth\Factory as AuthFactory;
use MacropaySolutions\Kernel\Contracts\Broadcasting\Factory as BroadcastFactory;
use MacropaySolutions\Kernel\Contracts\Bus\Dispatcher;
use MacropaySolutions\Kernel\Contracts\Cookie\Factory as CookieFactory;
use MacropaySolutions\Kernel\Contracts\Debug\ExceptionHandler;
use MacropaySolutions\Kernel\Support\HtmlString;

if (!function_exists('abort')) {
    /**
     * Throw an HttpException with the given data.
     *
     * @param int $code
     * @param string $message
     * @param array $headers
     * @return void
     *
     * @throws \MacropaySolutions\Kernel\Http\Base\HttpException
     * @throws \MacropaySolutions\Kernel\Http\Base\NotFoundHttpException
     */
    function abort($code, $message = '', array $headers = [])
    {
        app()->abort($code, $message, $headers);
    }
}

if (!function_exists('app')) {
    /**
     * Get the available container instance.
     *
     * @param string|null $make
     * @param array $parameters
     * @return mixed|\MacropaySolutions\Framework\Application
     */
    function app($make = null, array $parameters = [])
    {
        if (is_null($make)) {
            return Container::getInstance();
        }

        return Container::getInstance()->make($make, $parameters);
    }
}

if (!function_exists('auth')) {
    /**
     * Get the available auth instance.
     *
     * @param string|null $guard
     * @return \MacropaySolutions\Kernel\Contracts\Auth\Factory|\MacropaySolutions\Kernel\Contracts\Auth\Guard|\MacropaySolutions\Kernel\Contracts\Auth\StatefulGuard
     */
    function auth($guard = null)
    {
        if (is_null($guard)) {
            return app(AuthFactory::class);
        }

        return app(AuthFactory::class)->guard($guard);
    }
}

if (!function_exists('base_path')) {
    /**
     * Get the path to the base of the install.
     *
     * @param string $path
     * @return string
     */
    function base_path($path = '')
    {
        return app()->basePath() . ($path ? '/' . $path : $path);
    }
}

if (!function_exists('broadcast')) {
    /**
     * Begin broadcasting an event.
     *
     * @param mixed|null $event
     * @return \MacropaySolutions\Kernel\Broadcasting\PendingBroadcast
     */
    function broadcast($event = null)
    {
        return app(BroadcastFactory::class)->event($event);
    }
}

if (!function_exists('decrypt')) {
    /**
     * Decrypt the given value.
     *
     * @param string $value
     * @return string
     */
    function decrypt($value)
    {
        return app('encrypter')->decrypt($value);
    }
}

if (!function_exists('dispatch')) {
    /**
     * Dispatch a job to its appropriate handler.
     *
     * @param mixed $job
     * @return mixed
     */
    function dispatch($job)
    {
        if (\is_array($job)) {
            return new \MacropaySolutions\Framework\Bus\PendingCallableDispatch(
                \MacropaySolutions\Kernel\Queue\CallQueuedCallable::create($job)
            );
        }

        return new PendingDispatch($job);
    }
}

if (!function_exists('dispatch_now')) {
    /**
     * Dispatch a command to its appropriate handler in the current process.
     *
     * @param mixed $job
     * @param mixed $handler
     * @return mixed
     */
    function dispatch_now($job, $handler = null)
    {
        return app(Dispatcher::class)->dispatchNow($job, $handler);
    }
}

if (!function_exists('config')) {
    /**
     * Get / set the specified configuration value.
     *
     * If an array is passed as the key, we will assume you want to set an array of values.
     *
     * @param array|string|null $key
     * @param mixed $default
     * @return mixed
     */
    function config($key = null, $default = null)
    {
        if (is_null($key)) {
            return app('config');
        }

        if (is_array($key)) {
            return app('config')->set($key);
        }

        return app('config')->get($key, $default);
    }
}

if (!function_exists('database_path')) {
    /**
     * Get the path to the database directory of the install.
     *
     * @param string $path
     * @return string
     */
    function database_path($path = '')
    {
        return app()->databasePath($path);
    }
}

if (!function_exists('encrypt')) {
    /**
     * Encrypt the given value.
     */
    function encrypt(mixed $value, bool $serialize): string
    {
        return app('encrypter')->encrypt($value, $serialize);
    }
}

if (!function_exists('event')) {
    /**
     * Dispatch an event and call the listeners.
     *
     * @param object|string $event
     * @param mixed $payload
     * @param bool $halt
     * @return array|null
     */
    function event($event, $payload = [], $halt = false)
    {
        return app('events')->dispatch($event, $payload, $halt);
    }
}

if (!function_exists('info')) {
    /**
     * Write some information to the log.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    function info($message, $context = [])
    {
        return \app('log')->info($message, $context);
    }
}

if (!function_exists('redirect')) {
    /**
     * Get an instance of the redirector.
     *
     * @param string|null $to
     * @param int $status
     * @param array $headers
     * @param bool|null $secure
     * @return \MacropaySolutions\Framework\Http\Redirector|\MacropaySolutions\Kernel\Http\RedirectResponse
     */
    function redirect($to = null, $status = 302, $headers = [], $secure = null)
    {
        $redirector = new MacropaySolutions\Framework\Http\Redirector(app());

        if (is_null($to)) {
            return $redirector;
        }

        return $redirector->to($to, $status, $headers, $secure);
    }
}

if (!function_exists('report')) {
    /**
     * Report an exception.
     *
     * @param \Throwable $exception
     * @return void
     */
    function report(Throwable $exception)
    {
        app(ExceptionHandler::class)->report($exception);
    }
}

if (!function_exists('request')) {
    /**
     * Get an instance of the current request or an input item from the request.
     *
     * @param array|string|null $key
     * @param mixed $default
     * @return \MacropaySolutions\Kernel\Http\Request|string|array
     */
    function request($key = null, $default = null)
    {
        if (is_null($key)) {
            return app('request');
        }

        if (is_array($key)) {
            return app('request')->only($key);
        }

        $value = app('request')->__get($key);

        return is_null($value) ? value($default) : $value;
    }
}

if (!function_exists('resource_path')) {
    /**
     * Get the path to the resources folder.
     *
     * @param string $path
     * @return string
     */
    function resource_path($path = '')
    {
        return app()->resourcePath($path);
    }
}

if (!function_exists('response')) {
    /**
     * Return a new response from the application.
     *
     * @param string $content
     * @param int $status
     * @param array $headers
     * @return \MacropaySolutions\Kernel\Http\Response|\MacropaySolutions\Framework\Http\ResponseFactory
     */
    function response($content = '', $status = 200, array $headers = [])
    {
        $factory = \app(MacropaySolutions\Framework\Http\ResponseFactory::class);

        if (func_num_args() === 0) {
            return $factory;
        }

        return $factory->make($content, $status, $headers);
    }
}

if (!function_exists('route')) {
    /**
     * Generate a URL to a named route.
     *
     * @param string $name
     * @param array $parameters
     * @param bool|null $secure
     * @return string
     */
    function route($name, $parameters = [], $secure = null)
    {
        return app('url')->route($name, $parameters, $secure);
    }
}

if (!function_exists('storage_path')) {
    /**
     * Get the path to the storage folder.
     *
     * @param string $path
     * @return string
     */
    function storage_path($path = '')
    {
        return app()->storagePath($path);
    }
}

if (!function_exists('trans')) {
    /**
     * Translate the given message.
     *
     * @param string|null $id
     * @param array $replace
     * @param string|null $locale
     * @return \MacropaySolutions\Kernel\Contracts\Translation\Translator|string|array|null
     */
    function trans($id = null, $replace = [], $locale = null)
    {
        if (is_null($id)) {
            return app('translator');
        }

        return app('translator')->get($id, $replace, $locale);
    }
}

if (!function_exists('__')) {
    /**
     * Translate the given message.
     *
     * @param string $key
     * @param array $replace
     * @param string|null $locale
     * @return string|array|null
     */
    function __($key, $replace = [], $locale = null)
    {
        return app('translator')->get($key, $replace, $locale);
    }
}

if (!function_exists('trans_choice')) {
    /**
     * Translates the given message based on a count.
     *
     * @param string $id
     * @param int|array|\Countable $number
     * @param array $replace
     * @param string|null $locale
     * @return string
     */
    function trans_choice($id, $number, array $replace = [], $locale = null)
    {
        return app('translator')->choice($id, $number, $replace, $locale);
    }
}

if (!function_exists('url')) {
    /**
     * Generate a url for the application.
     *
     * @param string $path
     * @param mixed $parameters
     * @param bool|null $secure
     * @return string
     */
    function url($path = null, $parameters = [], $secure = null)
    {
        return app('url')->to($path, $parameters, $secure);
    }
}

if (!function_exists('validator')) {
    /**
     * Create a new Validator instance.
     *
     * @param array $data
     * @param array $rules
     * @param array $messages
     * @param array $customAttributes
     * @return \MacropaySolutions\Kernel\Contracts\Validation\Validator
     */
    function validator(array $data = [], array $rules = [], array $messages = [], array $customAttributes = [])
    {
        $factory = app('validator');

        if (func_num_args() === 0) {
            return $factory;
        }

        return $factory->make($data, $rules, $messages, $customAttributes);
    }
}

if (!function_exists('view')) {
    /**
     * Get the evaluated view contents for the given view.
     *
     * @param string $view
     * @param array $data
     * @param array $mergeData
     * @return \MacropaySolutions\Kernel\View\View
     */
    function view($view = null, $data = [], $mergeData = [])
    {
        $factory = app('view');

        if (func_num_args() === 0) {
            return $factory;
        }

        return $factory->make($view, $data, $mergeData);
    }
}

if (!\function_exists('arrayUniqueSortRegular')) {
    /**
     * Backward compatible array_unique($items, SORT_REGULAR) fix
     * see https://github.com/php/php-src/issues/20262#issuecomment-3441217772
     */
    function arrayUniqueSortRegular(array $items): array
    {
        $ui = $us = $u = $sp = [];

        foreach ($items as $k => $v) {
            if (\is_string($v)) {
                $us[$v] ??= $k;

                continue;
            }

            if (\is_int($v)) {
                $ui[$v] ??= $k;

                continue;
            }

            if (null === $v) {
                $sp['null'] ??= $k;

                continue;
            }

            if (false === $v) {
                $sp['false'] ??= $k;

                continue;
            }

            if (true === $v) {
                $sp['true'] ??= $k;

                continue;
            }

            $u[$k] = $v;
        }

        return \array_intersect_key(
            $items,
            \array_flip($ui) + \array_flip($us) + \array_flip($sp) + \array_unique($u, SORT_REGULAR)
        );
    }
}

if (!function_exists('di')) {
    /**
     * Safely resolve from container a class ONLY after the app has been booted.
     * If not, it will instantiate the classFqn only with $parameters as list
     */
    function di(string $classFqn, array $parametersList = []): mixed
    {
        $container = Container::getInstance();

        if ($container instanceof \MacropaySolutions\Framework\Application && $container->isBooted()) {
            return $container->make($classFqn, $parametersList);
        }

        if (
            \class_exists($classFqn)
            && (
                $parametersList === []
                || \array_is_list($parametersList)
            )
        ) {
            return new $classFqn(...$parametersList);
        }

        throw new \MacropaySolutions\Kernel\Contracts\Container\BindingResolutionException(
            'di function called before app booted for: ' . $classFqn . ' trace: ' . \json_encode(
                \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)
            )
        );
    }
}

if (!function_exists('session')) {
    /**
     * Get / set the specified session value.
     *
     * If an array is passed as the key, we will assume you want to set an array of values.
     *
     * @param array|string|null $key
     * @param mixed $default
     * @return mixed|\MacropaySolutions\Kernel\Session\Store|\MacropaySolutions\Kernel\Session\SessionManager
     */
    function session($key = null, $default = null)
    {
        if (is_null($key)) {
            return app('session');
        }

        if (is_array($key)) {
            return app('session')->put($key);
        }

        return app('session')->get($key, $default);
    }
}

if (!function_exists('cookie')) {
    /**
     * Create a new cookie instance.
     *
     * @param string|null $name
     * @param string|null $value
     * @param int $minutes
     * @param string|null $path
     * @param string|null $domain
     * @param bool|null $secure
     * @param bool $httpOnly
     * @param bool $raw
     * @param string|null $sameSite
     * @return \MacropaySolutions\Kernel\Cookie\CookieJar|\MacropaySolutions\Kernel\Http\Base\Cookie
     */
    function cookie(
        $name = null,
        $value = null,
        $minutes = 0,
        $path = null,
        $domain = null,
        $secure = null,
        $httpOnly = true,
        $raw = false,
        $sameSite = null
    ) {
        $cookie = app(CookieFactory::class);

        if (is_null($name)) {
            return $cookie;
        }

        return $cookie->make($name, $value, $minutes, $path, $domain, $secure, $httpOnly, $raw, $sameSite);
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Generate a CSRF token form field.
     *
     * @return \MacropaySolutions\Kernel\Support\HtmlString
     */
    function csrf_field()
    {
        return new HtmlString('<input type="hidden" name="_token" value="' . csrf_token() . '" autocomplete="off">');
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Get the CSRF token value.
     *
     * @return string
     *
     * @throws \RuntimeException
     */
    function csrf_token()
    {
        $session = app('session');

        if (isset($session)) {
            return $session->token();
        }

        throw new \RuntimeException('Application session store not set.');
    }
}

if (!\function_exists('appDate')) {
    function appDate(): \MacropaySolutions\Kernel\Support\DateFactory
    {
        if (\app()->bound('date')) {
            return \app('date');
        }

        return \app(\MacropaySolutions\Kernel\Support\DateFactory::class);
    }
}
