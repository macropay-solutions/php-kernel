<?php

namespace MacropaySolutions\Kernel\Contracts\Database\Obvious;

use MacropaySolutions\Kernel\Database\Obvious\Model;

interface SerializesCastableAttributes
{
    /**
     * Serialize the attribute when converting the model to an array.
     *
     * @param \MacropaySolutions\Kernel\Database\Obvious\Model $model
     * @param string $key
     * @param mixed $value
     * @param array $attributes
     * @return mixed
     */
    public function serialize(Model $model, string $key, mixed $value, array $attributes);
}
