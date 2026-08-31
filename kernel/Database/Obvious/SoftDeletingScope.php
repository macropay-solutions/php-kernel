<?php

namespace MacropaySolutions\Kernel\Database\Obvious;

class SoftDeletingScope implements Scope
{
    /**
     * All the extensions to be added to the builder.
     *
     * @var string[]
     */
    protected $extensions = [
        'restore',
        'restoreOrCreate',
        'createOrRestore',
        'withTrashed',
        'withoutTrashed',
        'onlyTrashed',
    ];

    /**
     * Apply the scope to a given Obvious query builder.
     *
     * @param \MacropaySolutions\Kernel\Database\Obvious\Builder $builder
     * @param \MacropaySolutions\Kernel\Database\Obvious\Model $model
     * @return void
     */
    public function apply(Builder $builder, Model $model)
    {
        $builder->whereNull($model->getQualifiedDeletedAtColumn());
    }

    /**
     * Extend the query builder with the needed functions.
     *
     * @param \MacropaySolutions\Kernel\Database\Obvious\Builder $builder
     * @return void
     */
    public function extend(Builder $builder)
    {
        foreach ($this->extensions as $extension) {
            $builder->addExtension($extension, [$this, $extension]);
        }

        $builder->onDelete([$this, 'performDelete']);
    }

    /**
     * Perform the soft delete for the builder.
     */
    public function performDelete(Builder $builder): int
    {
        $column = $this->getDeletedAtColumn($builder);

        return $builder->update([
            $column => \date($builder->getModel()::DELETED_AT_FORMAT),
        ]);
    }

    /**
     * Get the "deleted at" column for the builder.
     */
    protected function getDeletedAtColumn(Builder $builder): string
    {
        if (count((array)$builder->getQuery()->joins) > 0) {
            return $builder->getModel()->getQualifiedDeletedAtColumn();
        }

        return $builder->getModel()->getDeletedAtColumn();
    }

    /**
     * Restore a soft-deleted model instance.
     */
    public function restore(Builder $builder): int
    {
        $builder->withTrashed();

        return $builder->update([$builder->getModel()->getDeletedAtColumn() => null]);
    }

    /**
     * Restore or create a model instance.
     */
    public function restoreOrCreate(Builder $builder, array $attributes = [], array $values = []): Model
    {
        $builder->withTrashed();

        return tap($builder->firstOrCreate($attributes, $values), function ($instance) {
            $instance->restore();
        });
    }

    /**
     * Create or restore a model instance.
     */
    public function createOrRestore(Builder $builder, array $attributes = [], array $values = []): Model
    {
        $builder->withTrashed();

        return tap($builder->createOrFirst($attributes, $values), function ($instance) {
            $instance->restore();
        });
    }

    /**
     * Include soft-deleted records in the results.
     */
    public function withTrashed(Builder $builder, bool $withTrashed = true): Builder
    {
        if (!$withTrashed) {
            return $builder->withoutTrashed();
        }

        return $builder->withoutGlobalScope($this);
    }

    /**
     * Exclude soft-deleted records from the results.
     */
    public function withoutTrashed(Builder $builder): Builder
    {
        $model = $builder->getModel();

        $builder->withoutGlobalScope($this)->whereNull(
            $model->getQualifiedDeletedAtColumn()
        );

        return $builder;
    }

    /**
     * Return only soft-deleted records.
     */
    public function onlyTrashed(Builder $builder): Builder
    {
        $model = $builder->getModel();

        $builder->withoutGlobalScope($this)->whereNotNull(
            $model->getQualifiedDeletedAtColumn()
        );

        return $builder;
    }
}
