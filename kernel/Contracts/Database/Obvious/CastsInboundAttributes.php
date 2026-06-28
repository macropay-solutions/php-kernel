<?php

namespace MacropaySolutions\Kernel\Contracts\Database\Obvious;

use MacropaySolutions\Kernel\Database\Obvious\Model;

interface CastsInboundAttributes
{
    /**
     * Transform the attribute to its underlying model values.
     *
     * @param \MacropaySolutions\Kernel\Database\Obvious\Model $model
     * @param string $key
     * @param mixed $value
     * @param array $attributes
     * @return mixed
     */
    public function set(Model $model, string $key, mixed $value, array $attributes);
}
