<?php

namespace MacropaySolutions\Kernel\Contracts\Filesystem;

interface Factory
{
    /**
     * Get a filesystem implementation.
     *
     * @param string|null $name
     * @return \MacropaySolutions\Kernel\Contracts\Filesystem\Filesystem
     */
    public function disk($name = null);
}
