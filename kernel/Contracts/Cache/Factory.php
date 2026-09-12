<?php

namespace MacropaySolutions\Kernel\Contracts\Cache;

interface Factory
{
    /**
     * Get a cache store instance by name.
     *
     * @param string|null $name
     * @return \MacropaySolutions\Kernel\Contracts\Cache\Repository
     */
    public function store($name = null);

    /**
     * Dynamically call the default driver instance.
     */
    public function fwd(): \MacropaySolutions\Kernel\Contracts\Cache\Repository|LockProvider;
}
