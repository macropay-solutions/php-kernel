<?php

namespace MacropaySolutions\Kernel\Database\Obvious\Casts;

use BackedEnum;
use MacropaySolutions\Kernel\Contracts\Database\Obvious\Castable;
use MacropaySolutions\Kernel\Contracts\Database\Obvious\CastsAttributes;
use MacropaySolutions\Kernel\Support\Collection;

class AsEnumArrayObject implements Castable
{
    /**
     * Get the caster class to use when casting from / to this cast target.
     *
     * @template TEnum
     *
     * @param array{class-string<TEnum>} $arguments
     * @return \MacropaySolutions\Kernel\Contracts\Database\Obvious\CastsAttributes<\MacropaySolutions\Kernel\Database\Obvious\Casts\ArrayObject<array-key, TEnum>, iterable<TEnum>>
     */
    public static function castUsing(array $arguments)
    {
        return new class ($arguments) implements CastsAttributes {
            protected $arguments;

            public function __construct(array $arguments)
            {
                $this->arguments = $arguments;
            }

            public function get($model, $key, $value, $attributes)
            {
                if (!isset($attributes[$key])) {
                    return;
                }

                $data = Json::decode($attributes[$key]);

                if (!is_array($data)) {
                    return;
                }

                $enumClass = $this->arguments[0];

//                return new ArrayObject((new Collection($data))->map(function ($value) use ($enumClass) {
                return new ArrayObject(
                    \di(Collection::class, [$data])->map(function ($value) use ($enumClass) {
                        return is_subclass_of($enumClass, BackedEnum::class)
                            ? $enumClass::from($value)
                            : constant($enumClass . '::' . $value);
                    })->toArray()
                );
            }

            public function set($model, $key, $value, $attributes)
            {
                if ($value === null) {
                    return [$key => null];
                }

                $storable = [];

                foreach ($value as $enum) {
                    $storable[] = $this->getStorableEnumValue($enum);
                }

                return [$key => Json::encode($storable)];
            }

            public function serialize($model, string $key, $value, array $attributes)
            {
//                return (new Collection($value->getArrayCopy()))->map(function ($enum) {
                return \di(Collection::class, [$value->getArrayCopy()])->map(function ($enum) {
                    return $this->getStorableEnumValue($enum);
                })->toArray();
            }

            protected function getStorableEnumValue($enum)
            {
                if (is_string($enum) || is_int($enum)) {
                    return $enum;
                }

                return $enum instanceof BackedEnum ? $enum->value : $enum->name;
            }
        };
    }
}
