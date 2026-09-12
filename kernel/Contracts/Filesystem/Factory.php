<?php

namespace MacropaySolutions\Kernel\Contracts\Filesystem;

use MacropaySolutions\Kernel\Filesystem\FilesystemAdapter;

interface Factory
{
    /**
     * Get a filesystem implementation.
     *
     * @param string|null $name
     * @return \MacropaySolutions\Kernel\Contracts\Filesystem\Filesystem
     */
    public function disk($name = null);

    /**
     * Dynamically call the default driver instance.
     */
    public function fwd(): \MacropaySolutions\Kernel\Contracts\Filesystem\Filesystem|FilesystemAdapter;
}
