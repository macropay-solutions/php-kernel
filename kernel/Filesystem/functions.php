<?php

namespace MacropaySolutions\Kernel\Filesystem;

if (!function_exists('MacropaySolutions\Kernel\Filesystem\join_paths')) {
    /**
     * Join the given paths together.
     *
     * @param string|null $basePath
     * @param string ...$paths
     * @return string
     */
    function join_paths($basePath, ...$paths)
    {
        foreach ($paths as $index => $path) {
            if (empty($path)) {
                unset($paths[$index]);
            } else {
                $paths[$index] = DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
            }
        }

        return $basePath . implode('', $paths);
    }
}
