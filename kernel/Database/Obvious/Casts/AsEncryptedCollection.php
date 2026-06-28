<?php

namespace MacropaySolutions\Kernel\Database\Obvious\Casts;

use MacropaySolutions\Kernel\Contracts\Database\Obvious\Castable;
use MacropaySolutions\Kernel\Contracts\Database\Obvious\CastsAttributes;
use MacropaySolutions\Kernel\Support\Collection;
use InvalidArgumentException;

class AsEncryptedCollection implements Castable
{
    /**
     * Get the caster class to use when casting from / to this cast target.
     *
     * @param array $arguments
     * @return \MacropaySolutions\Kernel\Contracts\Database\Obvious\CastsAttributes<\MacropaySolutions\Kernel\Support\Collection<array-key, mixed>, iterable>
     */
    public static function castUsing(array $arguments)
    {
        return new class ($arguments) implements CastsAttributes {
            public function __construct(protected array $arguments)
            {
            }

            public function get($model, $key, $value, $attributes)
            {
                $collectionClass = $this->arguments[0] ?? Collection::class;

                if (!is_a($collectionClass, Collection::class, true)) {
                    throw new InvalidArgumentException('The provided class must extend [' . Collection::class . '].');
                }

                if (isset($attributes[$key])) {
                    return new $collectionClass(Json::decode(\app('encrypter')->decryptString($attributes[$key])));
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
        };
    }
}
