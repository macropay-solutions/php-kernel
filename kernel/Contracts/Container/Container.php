<?php

namespace MacropaySolutions\Kernel\Contracts\Container;

use Psr\Container\ContainerInterface;

interface Container extends ContainerInterface
{
    /**
     * Determine if the given abstract type has been bound.
     *
     * @param string $abstract
     * @return bool
     */
    public function bound($abstract);

    /**
     * Alias a type to a different name.
     *
     * @param string $abstract
     * @param string $alias
     * @return void
     *
     * @throws \LogicException
     */
    public function alias($abstract, $alias);

    /**
     * Assign a set of tags to a given binding.
     *
     * @param array|string $abstracts
     * @param array|mixed ...$tags
     * @return void
     */
    public function tag($abstracts, $tags);

    /**
     * Resolve all the bindings for a given tag.
     *
     * @param string $tag
     * @return iterable
     */
    public function tagged($tag);

    /**
     * Register a binding with the container.
     */
    public function bind(string $abstract, array|string|null $concrete = null, bool $shared = false): void;

    /**
     * Bind a callback to resolve with Container::call.
     */
    public function bindMethod(array|string $method, array $callback): void;

    /**
     * Register a binding if it hasn't already been registered.
     * @param string $abstract
     * @param array|string|null $concrete
     * @param ?bool $shared
     * @return void
     */
    public function bindIf($abstract, $concrete = null, $shared = false);

    /**
     * Register a shared binding in the container.
     */
    public function singleton($abstract, $concrete = null);

    /**
     * Register a shared binding if it hasn't already been registered.
     */
    public function singletonIf($abstract, $concrete = null);

    /**
     * Register a scoped binding in the container.
     */
    public function scoped($abstract, $concrete = null);

    /**
     * Register a scoped binding if it hasn't already been registered.
     */
    public function scopedIf($abstract, $concrete = null);

    /**
     * "Extend" an abstract type in the container.
     *
     * @throws \InvalidArgumentException
     */
    public function extend(string $abstract, array $closure): void;

    /**
     * Register an existing instance as shared in the container.
     *
     * @param string $abstract
     * @param mixed $instance
     * @return mixed
     */
    public function instance($abstract, $instance);

    /**
     * Flush the container of all bindings and resolved instances.
     *
     * @return void
     */
    public function flush();

    /**
     * Resolve the given type from the container.
     *
     * @param string $abstract
     * @param array $parameters
     * @return mixed
     *
     * @throws \MacropaySolutions\Kernel\Contracts\Container\BindingResolutionException
     */
    public function make($abstract, array $parameters = []);

    /**
     * Resolve the given type from the container.
     *
     * @throws BindingResolutionException
     * @throws CircularDependencyException
     * @throws \ReflectionException
     */
    public function makeWithoutAlias(string $abstract, array $parameters = []): mixed;

    /**
     * Call the given callable / class@method and inject its dependencies.
     *
     * @param callable|string $callback
     * @param array $parameters
     * @param string|null $defaultMethod
     * @return mixed
     */
    public function call($callback, array $parameters = [], ?string $defaultMethod = null);

    /**
     * Determine if the given abstract type has been resolved.
     */
    public function resolved(string $abstract): bool;

    /**
     * Register a new before resolving callback.
     */
    public function beforeResolving(array|string $abstract, array|null $callback = null): void;

    /**
     * Register a new resolving callback.
     */
    public function resolving(array|string $abstract, array|null $callback = null): void;

    /**
     * Register a new after resolving callback.
     * @return void
     */
    public function afterResolving(array|string $abstract, array|null $callback = null): void;
}
