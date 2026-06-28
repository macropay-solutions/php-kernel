<?php

namespace MacropaySolutions\Kernel\Database\Obvious;

interface Scope
{
    /**
     * Apply the scope to a given Obvious query builder.
     *
     * @param \MacropaySolutions\Kernel\Database\Obvious\Builder $builder
     * @param \MacropaySolutions\Kernel\Database\Obvious\Model $model
     * @return void
     */
    public function apply(Builder $builder, Model $model);
}
