<?php

namespace MacropaySolutions\Kernel\Database\Obvious\Casts;

use MacropaySolutions\Kernel\Contracts\Database\Obvious\Castable;
use MacropaySolutions\Kernel\Contracts\Database\Obvious\CastsAttributes;

class AsEncryptedArrayObject implements Castable
{
    /**
     * Get the caster class to use when casting from / to this cast target.
     *
     * @param array $arguments
     * @return \MacropaySolutions\Kernel\Contracts\Database\Obvious\CastsAttributes<\MacropaySolutions\Kernel\Database\Obvious\Casts\ArrayObject<array-key, mixed>, iterable>
     */
    public static function castUsing(array $arguments)
    {
        return new class implements CastsAttributes {
            public function get($model, $key, $value, $attributes)
            {
                if (isset($attributes[$key])) {
                    return new ArrayObject(Json::decode(\app('encrypter')->decryptString($attributes[$key])));
                }

                return null;
            }

            public function set($model, $key, $value, $attributes)
            {
                if (!is_null($value)) {
                    return [$key => \app('encrypter')->encryptString(Json::encode($value))];
                }

                return null;
            }

            public function serialize($model, string $key, $value, array $attributes)
            {
                return !is_null($value) ? $value->getArrayCopy() : null;
            }
        };
    }
}
