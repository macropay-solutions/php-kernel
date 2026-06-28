<?php

namespace MacropaySolutions\Kernel\Contracts\Database\Obvious;

interface DeviatesCastableAttributes
{
    /**
     * Increment the attribute.
     *
     * @param \MacropaySolutions\Kernel\Database\Obvious\Model $model
     * @param string $key
     * @param mixed $value
     * @param array $attributes
     * @return mixed
     */
    public function increment($model, string $key, $value, array $attributes);

    /**
     * Decrement the attribute.
     *
     * @param \MacropaySolutions\Kernel\Database\Obvious\Model $model
     * @param string $key
     * @param mixed $value
     * @param array $attributes
     * @return mixed
     */
    public function decrement($model, string $key, $value, array $attributes);
}
