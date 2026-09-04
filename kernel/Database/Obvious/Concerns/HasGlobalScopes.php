<?php

namespace MacropaySolutions\Kernel\Database\Obvious\Concerns;

use Closure;
use InvalidArgumentException;
use MacropaySolutions\Kernel\Database\Obvious\Scope;
use MacropaySolutions\Kernel\Support\Arr;

trait HasGlobalScopes
{
    /**
     * Register a new global scope on the model.
     *
     * @throws \InvalidArgumentException
     */
    public static function addGlobalScope(
        Scope|\Closure|string $scope,
        Scope|\Closure|null $implementation = null
    ): mixed {
        if (is_string($scope) && ($implementation instanceof Closure || $implementation instanceof Scope)) {
            return static::$globalScopes[static::class][$scope] = $implementation;
        } elseif ($scope instanceof Closure) {
            return static::$globalScopes[static::class][spl_object_hash($scope)] = $scope;
        } elseif ($scope instanceof Scope) {
            return static::$globalScopes[static::class][get_class($scope)] = $scope;
        } elseif (is_string($scope) && class_exists($scope) && is_subclass_of($scope, Scope::class)) {
            return static::$globalScopes[static::class][$scope] = new $scope();
        }

        throw new InvalidArgumentException(
            'Global scope must be an instance of Closure or Scope or be a class name of a class extending ' .
                Scope::class
        );
    }

    /**
     * Register multiple global scopes on the model.
     */
    public static function addGlobalScopes(array $scopes): void
    {
        foreach ($scopes as $key => $scope) {
            if (\is_string($key)) {
                static::addGlobalScope($key, $scope);

                continue;
            }

            static::addGlobalScope($scope);
        }
    }

    /**
     * Determine if a model has a global scope.
     */
    public static function hasGlobalScope(Scope|string $scope): bool
    {
        return null !== static::getGlobalScope($scope);
    }

    /**
     * Get a global scope registered with the model.
     */
    public static function getGlobalScope(Scope|string $scope): Scope|\Closure|null
    {
        if (\is_string($scope)) {
            return Arr::get(static::$globalScopes, static::class . '.' . $scope);
        }

        return Arr::get(
            static::$globalScopes,
            static::class . '.' . $scope::class
        );
    }

    /**
     * Get all the global scopes that are currently registered.
     */
    public static function getAllGlobalScopes(): array
    {
        return static::$globalScopes;
    }

    /**
     * Set the current global scopes.
     */
    public static function setAllGlobalScopes(array $scopes): void
    {
        static::$globalScopes = $scopes;
    }

    /**
     * Get the global scopes for this class instance.
     */
    public function getGlobalScopes(): array
    {
        return Arr::get(static::$globalScopes, static::class, []);
    }
}
