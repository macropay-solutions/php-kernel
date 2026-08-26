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
use MacropaySolutions\Kernel\Support\Str;

/**
 * Depends on
 * @mixin HasRelationships
 * Manually add this trait in your model if you want to use morphable relations
 */
trait HasPolymorphicRelationships
{
    /**
     * Define a polymorphic one-to-one relationship.
     *
     * @param string $related
     * @param string $name
     * @param string|null $type
     * @param string|null $id
     * @param string|null $localKey
     * @return \MacropaySolutions\Kernel\Database\Obvious\Relations\MorphOne
     */
    public function morphOne($related, $name, $type = null, $id = null, $localKey = null)
    {
        $instance = $this->newRelatedInstance($related);

        [$type, $id] = $this->getMorphs($name, $type, $id);

        $table = $instance->getTable();

        $localKey = $localKey ?: $this->getKeyName();

        return $this->newMorphOne($instance->newQuery(), $this, $table . '.' . $type, $table . '.' . $id, $localKey);
    }

    /**
     * Instantiate a new MorphOne relationship.
     *
     * @param \MacropaySolutions\Kernel\Database\Obvious\Builder $query
     * @param \MacropaySolutions\Kernel\Database\Obvious\Model $parent
     * @param string $type
     * @param string $id
     * @param string $localKey
     * @return \MacropaySolutions\Kernel\Database\Obvious\Relations\MorphOne
     */
    protected function newMorphOne(Builder $query, Model $parent, $type, $id, $localKey)
    {
//        return new MorphOne($query, $parent, $type, $id, $localKey);
        return \di(MorphOne::class, [$query, $parent, $type, $id, $localKey]);
    }

    /**
     * Define a polymorphic, inverse one-to-one or many relationship.
     */
    public function morphTo(
        ?string $name = null,
        ?string $type = null,
        ?string $id = null,
        ?string $ownerKey = null
    ): MorphTo {
        $name ??= static::$segregatedRelationNameBeingCalled ??
            throw new \RuntimeException('Relation name could not be resolved. Please pass it explicitly ' .
                'in the relation definition if you defined and used the relation as a model method.');

        [$type, $id] = $this->getMorphs(
            Str::snake($name),
            $type,
            $id
        );

        // If the type value is null it is probably safe to assume we're eager loading
        // the relationship. In this case we'll just pass in a dummy query where we
        // need to remove any eager loads that may already be defined on a model.
        return is_null($class = $this->getAttributeFromArray($type)) || $class === ''
            ? $this->morphEagerTo($name, $type, $id, $ownerKey)
            : $this->morphInstanceTo($class, $name, $type, $id, $ownerKey);
    }

    /**
     * Define a polymorphic, inverse one-to-one or many relationship.
     *
     * @param string $name
     * @param string $type
     * @param string $id
     * @param string $ownerKey
     * @return \MacropaySolutions\Kernel\Database\Obvious\Relations\MorphTo
     */
    protected function morphEagerTo($name, $type, $id, $ownerKey)
    {
        return $this->newMorphTo(
            $this->newQuery()->setEagerLoads([]),
            $this,
            $id,
            $ownerKey,
            $type,
            $name
        );
    }

    /**
     * Define a polymorphic, inverse one-to-one or many relationship.
     *
     * @param string $target
     * @param string $name
     * @param string $type
     * @param string $id
     * @param string $ownerKey
     * @return \MacropaySolutions\Kernel\Database\Obvious\Relations\MorphTo
     */
    protected function morphInstanceTo($target, $name, $type, $id, $ownerKey)
    {
        $instance = $this->newRelatedInstance(
            static::getActualClassNameForMorph($target)
        );

        return $this->newMorphTo(
            $instance->newQuery(),
            $this,
            $id,
            $ownerKey ?? $instance->getKeyName(),
            $type,
            $name
        );
    }

    /**
     * Instantiate a new MorphTo relationship.
     *
     * @param \MacropaySolutions\Kernel\Database\Obvious\Builder $query
     * @param \MacropaySolutions\Kernel\Database\Obvious\Model $parent
     * @param string $foreignKey
     * @param string $ownerKey
     * @param string $type
     * @param string $relation
     * @return \MacropaySolutions\Kernel\Database\Obvious\Relations\MorphTo
     */
    protected function newMorphTo(Builder $query, Model $parent, $foreignKey, $ownerKey, $type, $relation)
    {
//        return new MorphTo($query, $parent, $foreignKey, $ownerKey, $type, $relation);
        return \di(MorphTo::class, [$query, $parent, $foreignKey, $ownerKey, $type, $relation]);
    }

    /**
     * Retrieve the actual class name for a given morph class.
     *
     * @param string $class
     * @return string
     */
    public static function getActualClassNameForMorph($class)
    {
        return Arr::get(Relation::morphMap() ?: [], $class, $class);
    }

    /**
     * Define a polymorphic one-to-many relationship.
     *
     * @param string $related
     * @param string $name
     * @param string|null $type
     * @param string|null $id
     * @param string|null $localKey
     * @return \MacropaySolutions\Kernel\Database\Obvious\Relations\MorphMany
     */
    public function morphMany($related, $name, $type = null, $id = null, $localKey = null)
    {
        $instance = $this->newRelatedInstance($related);

        // Here we will gather up the morph type and ID for the relationship so that we
        // can properly query the intermediate table of a relation. Finally, we will
        // get the table and create the relationship instances for the developers.
        [$type, $id] = $this->getMorphs($name, $type, $id);

        $table = $instance->getTable();

        $localKey = $localKey ?: $this->getKeyName();

        return $this->newMorphMany($instance->newQuery(), $this, $table . '.' . $type, $table . '.' . $id, $localKey);
    }

    /**
     * Instantiate a new MorphMany relationship.
     *
     * @param \MacropaySolutions\Kernel\Database\Obvious\Builder $query
     * @param \MacropaySolutions\Kernel\Database\Obvious\Model $parent
     * @param string $type
     * @param string $id
     * @param string $localKey
     * @return \MacropaySolutions\Kernel\Database\Obvious\Relations\MorphMany
     */
    protected function newMorphMany(Builder $query, Model $parent, $type, $id, $localKey)
    {
//        return new MorphMany($query, $parent, $type, $id, $localKey);
        return \di(MorphMany::class, [$query, $parent, $type, $id, $localKey]);
    }

    /**
     * Define a polymorphic many-to-many relationship.
     *
     * @param string $related
     * @param string $name
     * @param string|null $table
     * @param string|null $foreignPivotKey
     * @param string|null $relatedPivotKey
     * @param string|null $parentKey
     * @param string|null $relatedKey
     * @param string|null $relation
     * @param bool $inverse
     * @return \MacropaySolutions\Kernel\Database\Obvious\Relations\MorphToMany
     */
    public function morphToMany(
        string $related,
        string $name,
        ?string $table = null,
        ?string $foreignPivotKey = null,
        ?string $relatedPivotKey = null,
        ?string $parentKey = null,
        ?string $relatedKey = null,
        ?string $relation = null,
        bool $inverse = false
    ) {
        $relation ??= static::$segregatedRelationNameBeingCalled ??
            throw new \RuntimeException('Relation name could not be resolved. Please pass it explicitly ' .
                'in the relation definition if you defined and used the relation as a model method.');

        // First, we will need to determine the foreign key and "other key" for the
        // relationship. Once we have determined the keys we will make the query
        // instances, as well as the relationship instances we need for these.
        $instance = $this->newRelatedInstance($related);

        $foreignPivotKey = $foreignPivotKey ?: $name . '_id';

        $relatedPivotKey = $relatedPivotKey ?: $instance->getForeignKey();

        // Now we're ready to create a new query builder for the related model and
        // the relationship instances for this relation. This relation will set
        // appropriate query constraints then entirely manage the hydrations.
        if (!$table) {
            $words = preg_split('/(_)/u', $name, -1, PREG_SPLIT_DELIM_CAPTURE);

            $lastWord = array_pop($words);

            $table = implode('', $words) . Str::plural($lastWord);
        }

        return $this->newMorphToMany(
            $instance->newQuery(),
            $this,
            $name,
            $table,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey ?: $this->getKeyName(),
            $relatedKey ?: $instance->getKeyName(),
            $relation,
            $inverse
        );
    }

    /**
     * Instantiate a new MorphToMany relationship.
     *
     * @param \MacropaySolutions\Kernel\Database\Obvious\Builder $query
     * @param \MacropaySolutions\Kernel\Database\Obvious\Model $parent
     * @param string $name
     * @param string $table
     * @param string $foreignPivotKey
     * @param string $relatedPivotKey
     * @param string $parentKey
     * @param string $relatedKey
     * @param string|null $relationName
     * @param bool $inverse
     * @return \MacropaySolutions\Kernel\Database\Obvious\Relations\MorphToMany
     */
    protected function newMorphToMany(
        Builder $query,
        Model $parent,
        $name,
        $table,
        $foreignPivotKey,
        $relatedPivotKey,
        $parentKey,
        $relatedKey,
        $relationName = null,
        $inverse = false
    ) {
//  return new MorphToMany($query, $parent, $name, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey,
//            $relationName, $inverse);
        return \di(MorphToMany::class, [
            $query,
            $parent,
            $name,
            $table,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey,
            $relatedKey,
            $relationName,
            $inverse,
        ]);
    }

    /**
     * Define a polymorphic, inverse many-to-many relationship.
     *
     * @param string $related
     * @param string $name
     * @param string|null $table
     * @param string|null $foreignPivotKey
     * @param string|null $relatedPivotKey
     * @param string|null $parentKey
     * @param string|null $relatedKey
     * @param string|null $relation
     * @return \MacropaySolutions\Kernel\Database\Obvious\Relations\MorphToMany
     */
    public function morphedByMany(
        $related,
        $name,
        $table = null,
        $foreignPivotKey = null,
        $relatedPivotKey = null,
        $parentKey = null,
        $relatedKey = null,
        $relation = null
    ) {
        $foreignPivotKey = $foreignPivotKey ?: $this->getForeignKey();

        // For the inverse of the polymorphic many-to-many relations, we will change
        // the way we determine the foreign and other keys, as it is the opposite
        // of the morph-to-many method since we're figuring out these inverses.
        $relatedPivotKey = $relatedPivotKey ?: $name . '_id';

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

    /**
     * Get the polymorphic relationship columns.
     *
     * @param string $name
     * @param string $type
     * @param string $id
     * @return array
     */
    protected function getMorphs($name, $type, $id)
    {
        return [$type ?: $name . '_type', $id ?: $name . '_id'];
    }

    /**
     * Get the class name for polymorphic relations.
     *
     * @return string
     */
    public function getMorphClass()
    {
        $morphMap = Relation::morphMap();

        if (!empty($morphMap) && in_array(static::class, $morphMap)) {
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
