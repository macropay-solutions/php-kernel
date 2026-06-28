<?php

namespace MacropaySolutions\Kernel\Auth\Middleware;

use Closure;
use MacropaySolutions\Kernel\Contracts\Auth\Access\Gate;
use MacropaySolutions\Kernel\Database\Obvious\Model;

class Authorize
{
    /**
     * The gate instance.
     *
     * @var \MacropaySolutions\Kernel\Contracts\Auth\Access\Gate
     */
    protected $gate;

    /**
     * Create a new middleware instance.
     *
     * @param \MacropaySolutions\Kernel\Contracts\Auth\Access\Gate $gate
     * @return void
     */
    public function __construct(Gate $gate)
    {
        $this->gate = $gate;
    }

    /**
     * Specify the ability and models for the middleware.
     *
     * @param string $ability
     * @param string ...$models
     * @return string
     */
    public static function using($ability, ...$models)
    {
        return static::class . ':' . implode(',', [$ability, ...$models]);
    }

    /**
     * Handle an incoming request.
     *
     * @param \MacropaySolutions\Kernel\Http\Request $request
     * @param \Closure $next
     * @param string $ability
     * @param array|null ...$models
     * @return mixed
     *
     * @throws \MacropaySolutions\Kernel\Auth\AuthenticationException
     * @throws \MacropaySolutions\Kernel\Auth\Access\AuthorizationException
     */
    public function handle($request, Closure $next, $ability, ...$models)
    {
        $this->gate->authorize($ability, $this->getGateArguments($request, $models));

        return $next($request);
    }

    /**
     * Get the arguments parameter for the gate.
     *
     * @param \MacropaySolutions\Kernel\Http\Request $request
     * @param array|null $models
     * @return \MacropaySolutions\Kernel\Database\Obvious\Model|array|string
     */
    protected function getGateArguments($request, $models)
    {
        if (is_null($models)) {
            return [];
        }

        return collect($models)->map(function ($model) use ($request) {
            return $model instanceof Model ? $model : $this->getModel($request, $model);
        })->all();
    }

    /**
     * Get the model to authorize.
     *
     * @param \MacropaySolutions\Kernel\Http\Request $request
     * @param string $model
     * @return \MacropaySolutions\Kernel\Database\Obvious\Model|string
     */
    protected function getModel($request, $model)
    {
        if ($this->isClassName($model)) {
            return trim($model);
        }

        return $request->route($model, null) ??
            ((preg_match("/^['\"](.*)['\"]$/", trim($model), $matches)) ? $matches[1] : null);
    }

    /**
     * Checks if the given string looks like a fully qualified class name.
     *
     * @param string $value
     * @return bool
     */
    protected function isClassName($value)
    {
        return str_contains($value, '\\');
    }
}
