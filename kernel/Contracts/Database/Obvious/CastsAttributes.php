<?php

namespace MacropaySolutions\Kernel\Contracts\Database\Obvious;

use MacropaySolutions\Kernel\Database\Obvious\Model;

/**
 * @template TGet
 * @template TSet
 */
interface CastsAttributes
{
    /**
     * Transform the attribute from the underlying model values.
     *
     * @param \MacropaySolutions\Kernel\Database\Obvious\Model $model
     * @param string $key
     * @param mixed $value
     * @param array<string, mixed> $attributes
     * @return TGet|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes);

    /**
     * Transform the attribute to its underlying model values.
     *
     * @param \MacropaySolutions\Kernel\Database\Obvious\Model $model
     * @param string $key
     * @param TSet|null $value
     * @param array<string, mixed> $attributes
     * @return mixed
     */
    public function set(Model $model, string $key, mixed $value, array $attributes);
}
