<?php

namespace MacropaySolutions\Kernel\Database\Obvious\Concerns;

use MacropaySolutions\Kernel\Database\ClassMorphViolationException;
use MacropaySolutions\Kernel\Database\Obvious\Builder;
use MacropaySolutions\Kernel\Database\Obvious\Model;
use MacropaySolutions\Kernel\Database\Obvious\Relations\MorphMany;
use MacropaySolutions\Kernel\Database\Obvious\Relations\MorphOne;
use MacropaySolutions\Kernel\Database\Obvious\Relations\MorphTo;
use MacropaySolutions\Kernel\Database\Obvious\Relations\MorphToMany;
use MacropaySolutions\Kernel\Database\Obvious\Relations\Pivot;
use MacropaySolutions\Kernel\Database\Obvious\Relations\Relation;
use MacropaySolutions\Kernel\Support\Arr;

/**
 * Depends on
 * @mixin HasRelationships
 * Manually add this trait in your model if you want to use morphable relations
 */
trait HasPolymorphicRelationships
{
    /**
     * Define a polymorphic one-to-one relationship.
     */
    protected function morphOne(string $related, string $name, string $type, string $id, string $localKey): MorphOne
    {
        $instance = $this->newRelatedInstance($related);

        $table = $instance->getTable();

        return \di(MorphOne::class, [$instance->newQuery(), $this, $table . '.' . $type, $table . '.' . $id, $localKey]);
    }

    /**
     * Define a polymorphic, inverse one-to-one or many relationship.
     */
    protected function morphTo(
        string $type,
        string $id,
        string $ownerKey,
        ?string $name = null
    ): MorphTo {
        $name ??= static::$segregatedRelationNameBeingCalled ??
            throw new \RuntimeException('Relation name could not be resolved. Please pass it explicitly ' .
                'in the relation definition if you defined and used the relation as a model method.');

        // If the type value is null it is probably safe to assume we're eager loading
        // the relationship. In this case we'll just pass in a dummy query where we
        // need to remove any eager loads that may already be defined on a model.
        return is_null($class = $this->getAttributeFromArray($type)) || $class === ''
            ? $this->morphEagerTo($name, $type, $id, $ownerKey)
            : $this->morphInstanceTo($class, $name, $type, $id, $ownerKey);
    }

    protected function morphEagerTo(string $name, string $type, string $id, string $ownerKey): MorphTo
    {
        return \di(MorphTo::class, [
            $this->newQuery()->setEagerLoads([]),
            $this,
            $id,
            $ownerKey,
            $type,
            $name
        ]);
    }

    protected function morphInstanceTo(string $target, string $name, string $type, string $id, string $ownerKey): MorphTo
    {
        $instance = $this->newRelatedInstance(
            static::getActualClassNameForMorph($target)
        );

        return \di(MorphTo::class, [
            $instance->newQuery(),
            $this,
            $id,
            $ownerKey,
            $type,
            $name
        ]);
    }

    public static function getActualClassNameForMorph(string $class): string
    {
        return Arr::get(Relation::morphMap() ?: [], $class, $class);
    }

    /**
     * Define a polymorphic one-to-many relationship.
     */
    protected function morphMany(string $related, string $name, string $type, string $id, string $localKey): MorphMany
    {
        $instance = $this->newRelatedInstance($related);

        $table = $instance->getTable();

        return \di(MorphMany::class, [$instance->newQuery(), $this, $table . '.' . $type, $table . '.' . $id, $localKey]);
    }

    /**
     * Define a polymorphic many-to-many relationship.
     */
    protected function morphToMany(
        string $related,
        string $name,
        string $table,
        string $foreignPivotKey,
        string $relatedPivotKey,
        string $parentKey,
        string $relatedKey,
        ?string $relation = null,
        bool $inverse = false
    ): MorphToMany {
        $relation ??= static::$segregatedRelationNameBeingCalled ??
            throw new \RuntimeException('Relation name could not be resolved. Please pass it explicitly ' .
                'in the relation definition if you defined and used the relation as a model method.');

        $instance = $this->newRelatedInstance($related);

        return \di(MorphToMany::class, [
            $instance->newQuery(),
            $this,
            $name,
            $table,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey,
            $relatedKey,
            $relation,
            $inverse,
        ]);
    }

    /**
     * Define a polymorphic, inverse many-to-many relationship.
     */
    protected function morphedByMany(
        string $related,
        string $name,
        string $table,
        string $foreignPivotKey,
        string $relatedPivotKey,
        string $parentKey,
        string $relatedKey,
        ?string $relation = null
    ): MorphToMany {
        return $this->morphToMany(
            $related,
            $name,
            $table,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey,
            $relatedKey,
            $relation,
            true
        );
    }

    public function getMorphClass(): string
    {
        $morphMap = Relation::morphMap();

        if (!empty($morphMap) && in_array(static::class, $morphMap, true)) {
            return array_search(static::class, $morphMap, true);
        }

        if (static::class === Pivot::class) {
            return static::class;
        }

        if (Relation::requiresMorphMap()) {
            throw new ClassMorphViolationException($this);
        }

        return static::class;
    }
}
