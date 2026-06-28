<?php

namespace MacropaySolutions\Kernel\Database\Obvious\Casts;

use MacropaySolutions\Kernel\Contracts\Database\Obvious\Castable;
use MacropaySolutions\Kernel\Contracts\Database\Obvious\CastsAttributes;
use MacropaySolutions\Kernel\Support\Str;

class AsStringable implements Castable
{
    /**
     * Get the caster class to use when casting from / to this cast target.
     *
     * @param array $arguments
     * @return \MacropaySolutions\Kernel\Contracts\Database\Obvious\CastsAttributes<\MacropaySolutions\Kernel\Support\Stringable, string|\Stringable>
     */
    public static function castUsing(array $arguments)
    {
        return new class implements CastsAttributes {
            public function get($model, $key, $value, $attributes)
            {
                return isset($value) ? Str::of($value) : null;
            }

            public function set($model, $key, $value, $attributes)
            {
                return isset($value) ? (string)$value : null;
            }
        };
    }
}
