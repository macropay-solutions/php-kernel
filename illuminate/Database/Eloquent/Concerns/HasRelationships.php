<?php

namespace Illuminate\Database\Eloquent\Concerns;

use Closure;
use Illuminate\Database\ClassMorphViolationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\PendingHasThroughRelationship;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

trait HasRelationships
{
    /**
     * The static cache of relationship blueprints across all model instances.
     * Stored statically so X instances share the same number of closures per model.
     * Structured as: [ModelFqn => [RelationName => Closure]]
     */
    private static array $segregatedRelationsGlobalMap = [];

    /**
     * The static cache for relation ReflectionFunction
     * Structured as: [ModelFqn => [RelationName => false|\ReflectionFunction]]
     */
    private static array $segregatedRelationsReflectionFunctionsMap = [];

    private static ?string $segregatedRelationNameBeingCalled = null;

    public ?string $nowEagerLoadingRelationNameWithNoConstraints = null;

    /**
     * The loaded relationships for the model.
     *
     * @var array
     */
    protected $relations = [];

    /**
     * The relationships that should be touched on save.
     *
     * @var array
     */
    protected $touches = [];

    /**
     * The many to many relationship methods.
     *
     * @var string[]
     */
    public static $manyMethods = [
        'belongsToMany',
        'morphToMany',
        'morphedByMany',
    ];

    /**
     * The relation resolver callbacks.
     *
     * @var array
     */
    protected static $relationResolvers = [];

    public static function getSegregatedRelationNameBeingCalled(): ?string
    {
        return self::$segregatedRelationNameBeingCalled;
    }

    /**
     * Get the dynamic relation resolver if defined or inherited, or return null.
     *
     * @param string $class
     * @param string $key
     * @return ?Closure
     */
    public function relationResolver($class, $key)
    {
        if ([] === static::$relationResolvers) {
            return null;
        }

        if (($resolver = static::$relationResolvers[$class][$key] ?? null) instanceof Closure) {
            return $resolver;
        }

        if ($parent = get_parent_class($class)) {
            if (($resolver = $this->relationResolver($parent, $key)) instanceof Closure) {
                static::$relationResolvers[$class][$key] = $resolver;
            }

            return $resolver;
        }

        return null;
    }

    /**
     * Define a dynamic relation resolver.
     *
     * @param string $name
     * @param \Closure $callback
     * @return void
     */
    public static function resolveRelationUsing($name, Closure $callback)
    {
        try {
            $callback = $callback->bindTo(null, static::class) ?? $callback;
        } catch (\Throwable) {
            // Keep original closure if locked by php if inside a nonstatic closure already (db transaction closure)
        }

        static::$relationResolvers = array_replace_recursive(
            static::$relationResolvers,
            [static::class => [$name => $callback]]
        );
    }

    /**
     * Define a one-to-one relationship.
     *
     * @param string $related
     * @param string|null $foreignKey
     * @param string|null $localKey
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function hasOne($related, $foreignKey = null, $localKey = null)
    {
        $instance = $this->newRelatedInstance($related);

        $foreignKey = $foreignKey ?: $this->getForeignKey();

        $localKey = $localKey ?: $this->getKeyName();

        return $this->newHasOne($instance->newQuery(), $this, $instance->getTable() . '.' . $foreignKey, $localKey);
    }

    /**
     * Instantiate a new HasOne relationship.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Illuminate\Database\Eloquent\Model $parent
     * @param string $foreignKey
     * @param string $localKey
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    protected function newHasOne(Builder $query, Model $parent, $foreignKey, $localKey)
    {
//        return new HasOne($query, $parent, $foreignKey, $localKey);
        return \di(HasOne::class, [$query, $parent, $foreignKey, $localKey]);
    }

    /**
     * Define a has-one-through relationship.
     *
     * @param string $related
     * @param string $through
     * @param string|null $firstKey
     * @param string|null $secondKey
     * @param string|null $localKey
     * @param string|null $secondLocalKey
     * @return \Illuminate\Database\Eloquent\Relations\HasOneThrough
     */
    public function hasOneThrough(
        $related,
        $through,
        $firstKey = null,
        $secondKey = null,
        $localKey = null,
        $secondLocalKey = null
    ) {
        $through = $this->newRelatedThroughInstance($through);

        $firstKey = $firstKey ?: $this->getForeignKey();

        $secondKey = $secondKey ?: $through->getForeignKey();

        return $this->newHasOneThrough(
            $this->newRelatedInstance($related)->newQuery(),
            $this,
            $through,
            $firstKey,
            $secondKey,
            $localKey ?: $this->getKeyName(),
            $secondLocalKey ?: $through->getKeyName()
        );
    }

    /**
     * Instantiate a new HasOneThrough relationship.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Illuminate\Database\Eloquent\Model $farParent
     * @param \Illuminate\Database\Eloquent\Model $throughParent
     * @param string $firstKey
     * @param string $secondKey
     * @param string $localKey
     * @param string $secondLocalKey
     * @return \Illuminate\Database\Eloquent\Relations\HasOneThrough
     */
    protected function newHasOneThrough(
        Builder $query,
        Model $farParent,
        Model $throughParent,
        $firstKey,
        $secondKey,
        $localKey,
        $secondLocalKey
    ) {
//      return new HasOneThrough($query, $farParent, $throughParent, $firstKey, $secondKey, $localKey, $secondLocalKey);
        return \di(
            HasOneThrough::class,
            [$query, $farParent, $throughParent, $firstKey, $secondKey, $localKey, $secondLocalKey]
        );
    }

    /**
     * Define a polymorphic one-to-one relationship.
     *
     * @param string $related
     * @param string $name
     * @param string|null $type
     * @param string|null $id
     * @param string|null $localKey
     * @return \Illuminate\Database\Eloquent\Relations\MorphOne
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
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Illuminate\Database\Eloquent\Model $parent
     * @param string $type
     * @param string $id
     * @param string $localKey
     * @return \Illuminate\Database\Eloquent\Relations\MorphOne
     */
    protected function newMorphOne(Builder $query, Model $parent, $type, $id, $localKey)
    {
//        return new MorphOne($query, $parent, $type, $id, $localKey);
        return \di(MorphOne::class, [$query, $parent, $type, $id, $localKey]);
    }

    /**
     * Define an inverse one-to-one or many relationship.
     *
     * @param string $related
     * @param string|null $foreignKey
     * @param string|null $ownerKey
     * @param string|null $relation
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function belongsTo($related, $foreignKey = null, $ownerKey = null, $relation = null)
    {
        // If no relation name was given, we will use this debug backtrace to extract
        // the calling method's name and use that as the relationship name as most
        // of the time this will be what we desire to use for the relationships.
        if ('' === (string)$relation || \str_contains($relation, '{closure}')) {
            $relation = $this->guessBelongsToRelation();
        }

        $instance = $this->newRelatedInstance($related);

        // If no foreign key was supplied, we can use a backtrace to guess the proper
        // foreign key name by using the name of the relationship function, which
        // when combined with an "_id" should conventionally match the columns.
        if (is_null($foreignKey)) {
            $foreignKey = Str::snake($relation) . '_' . $instance->getKeyName();
        }

        // Once we have the foreign key names we'll just create a new Eloquent query
        // for the related models and return the relationship instance which will
        // actually be responsible for retrieving and hydrating every relation.
        $ownerKey = $ownerKey ?: $instance->getKeyName();

        return $this->newBelongsTo(
            $instance->newQuery(),
            $this,
            $foreignKey,
            $ownerKey,
            $relation
        );
    }

    /**
     * Instantiate a new BelongsTo relationship.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Illuminate\Database\Eloquent\Model $child
     * @param string $foreignKey
     * @param string $ownerKey
     * @param string $relation
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    protected function newBelongsTo(Builder $query, Model $child, $foreignKey, $ownerKey, $relation)
    {
//        return new BelongsTo($query, $child, $foreignKey, $ownerKey, $relation);
        return \di(BelongsTo::class, [$query, $child, $foreignKey, $ownerKey, $relation]);
    }

    /**
     * Define a polymorphic, inverse one-to-one or many relationship.
     *
     * @param string|null $name
     * @param string|null $type
     * @param string|null $id
     * @param string|null $ownerKey
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function morphTo($name = null, $type = null, $id = null, $ownerKey = null)
    {
        // If no name is provided, we will use the backtrace to get the function name
        // since that is most likely the name of the polymorphic interface. We can
        // use that to get both the class and foreign key that will be utilized.
        if ('' === (string)$name || \str_contains($name, '{closure}')) {
            $name = $this->guessBelongsToRelation();
        }

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
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
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
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
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
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Illuminate\Database\Eloquent\Model $parent
     * @param string $foreignKey
     * @param string $ownerKey
     * @param string $type
     * @param string $relation
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
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
     * Guess the "belongs to" relationship name.
     *
     * @return string
     */
    protected function guessBelongsToRelation()
    {
        [$one, $two, $caller] = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);

        $relation = (string)($caller['function'] ?? '');

        if (
            ('' === $relation || \str_contains($relation, '{closure}'))
            && isset(static::$segregatedRelationNameBeingCalled)
        ) {
            return static::$segregatedRelationNameBeingCalled;
        }

        return $relation;
    }

    /**
     * Create a pending has-many-through or has-one-through relationship.
     *
     * @param string|HasMany|\Illuminate\Database\Eloquent\Relations\HasOne $relationship
     * @return \Illuminate\Database\Eloquent\PendingHasThroughRelationship
     */
    public function through($relationship)
    {
        if (is_string($relationship)) {
            $relationship = $this->callSegregatedRelation($relationship);
        }

        return new PendingHasThroughRelationship($this, $relationship);
    }

    /**
     * Define a one-to-many relationship.
     *
     * @param string $related
     * @param string|null $foreignKey
     * @param string|null $localKey
     * @return HasMany
     */
    public function hasMany($related, $foreignKey = null, $localKey = null)
    {
        $instance = $this->newRelatedInstance($related);

        $foreignKey = $foreignKey ?: $this->getForeignKey();

        $localKey = $localKey ?: $this->getKeyName();

        return $this->newHasMany(
            $instance->newQuery(),
            $this,
            $instance->getTable() . '.' . $foreignKey,
            $localKey
        );
    }

    /**
     * Instantiate a new HasMany relationship.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Illuminate\Database\Eloquent\Model $parent
     * @param string $foreignKey
     * @param string $localKey
     * @return HasMany
     */
    protected function newHasMany(Builder $query, Model $parent, $foreignKey, $localKey)
    {
//        return new HasMany($query, $parent, $foreignKey, $localKey);
        return \di(HasMany::class, [$query, $parent, $foreignKey, $localKey]);
    }

    /**
     * Define a has-many-through relationship.
     *
     * @param string $related
     * @param string $through
     * @param string|null $firstKey
     * @param string|null $secondKey
     * @param string|null $localKey
     * @param string|null $secondLocalKey
     * @return \Illuminate\Database\Eloquent\Relations\HasManyThrough
     */
    public function hasManyThrough(
        $related,
        $through,
        $firstKey = null,
        $secondKey = null,
        $localKey = null,
        $secondLocalKey = null
    ) {
        $through = $this->newRelatedThroughInstance($through);

        $firstKey = $firstKey ?: $this->getForeignKey();

        $secondKey = $secondKey ?: $through->getForeignKey();

        return $this->newHasManyThrough(
            $this->newRelatedInstance($related)->newQuery(),
            $this,
            $through,
            $firstKey,
            $secondKey,
            $localKey ?: $this->getKeyName(),
            $secondLocalKey ?: $through->getKeyName()
        );
    }

    /**
     * Instantiate a new HasManyThrough relationship.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Illuminate\Database\Eloquent\Model $farParent
     * @param \Illuminate\Database\Eloquent\Model $throughParent
     * @param string $firstKey
     * @param string $secondKey
     * @param string $localKey
     * @param string $secondLocalKey
     * @return \Illuminate\Database\Eloquent\Relations\HasManyThrough
     */
    protected function newHasManyThrough(
        Builder $query,
        Model $farParent,
        Model $throughParent,
        $firstKey,
        $secondKey,
        $localKey,
        $secondLocalKey
    ) {
//     return new HasManyThrough($query, $farParent, $throughParent, $firstKey, $secondKey, $localKey, $secondLocalKey);
        return \di(
            HasManyThrough::class,
            [$query, $farParent, $throughParent, $firstKey, $secondKey, $localKey, $secondLocalKey]
        );
    }

    /**
     * Define a polymorphic one-to-many relationship.
     *
     * @param string $related
     * @param string $name
     * @param string|null $type
     * @param string|null $id
     * @param string|null $localKey
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
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
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Illuminate\Database\Eloquent\Model $parent
     * @param string $type
     * @param string $id
     * @param string $localKey
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    protected function newMorphMany(Builder $query, Model $parent, $type, $id, $localKey)
    {
//        return new MorphMany($query, $parent, $type, $id, $localKey);
        return \di(MorphMany::class, [$query, $parent, $type, $id, $localKey]);
    }

    /**
     * Define a many-to-many relationship.
     *
     * @param string $related
     * @param string|class-string<\Illuminate\Database\Eloquent\Model>|null $table
     * @param string|null $foreignPivotKey
     * @param string|null $relatedPivotKey
     * @param string|null $parentKey
     * @param string|null $relatedKey
     * @param string|null $relation
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function belongsToMany(
        $related,
        $table = null,
        $foreignPivotKey = null,
        $relatedPivotKey = null,
        $parentKey = null,
        $relatedKey = null,
        $relation = null
    ) {
        // If no relationship name was passed, we will pull backtraces to get the
        // name of the calling function. We will use that function name as the
        // title of this relation since that is a great convention to apply.
        if ('' === (string)$relation || \str_contains($relation, '{closure}')) {
            $relation = $this->guessBelongsToManyRelation();
        }

        // First, we'll need to determine the foreign key and "other key" for the
        // relationship. Once we have determined the keys we'll make the query
        // instances as well as the relationship instances we need for this.
        $instance = $this->newRelatedInstance($related);

        $foreignPivotKey = $foreignPivotKey ?: $this->getForeignKey();

        $relatedPivotKey = $relatedPivotKey ?: $instance->getForeignKey();

        // If no table name was provided, we can guess it by concatenating the two
        // models using underscores in alphabetical order. The two model names
        // are transformed to snake case from their default CamelCase also.
        if (is_null($table)) {
            $table = $this->joiningTable($related, $instance);
        }

        return $this->newBelongsToMany(
            $instance->newQuery(),
            $this,
            $table,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey ?: $this->getKeyName(),
            $relatedKey ?: $instance->getKeyName(),
            $relation
        );
    }

    /**
     * Instantiate a new BelongsToMany relationship.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Illuminate\Database\Eloquent\Model $parent
     * @param string|class-string<\Illuminate\Database\Eloquent\Model> $table
     * @param string $foreignPivotKey
     * @param string $relatedPivotKey
     * @param string $parentKey
     * @param string $relatedKey
     * @param string|null $relationName
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    protected function newBelongsToMany(
        Builder $query,
        Model $parent,
        $table,
        $foreignPivotKey,
        $relatedPivotKey,
        $parentKey,
        $relatedKey,
        $relationName = null
    ) {
        //return new BelongsToMany(
        //    $query,
        //    $parent,
        //    $table,
        //    $foreignPivotKey,
        //    $relatedPivotKey,
        //    $parentKey,
        //    $relatedKey,
        //    $relationName
        //);
        return \di(
            BelongsToMany::class,
            [$query, $parent, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey, $relationName]
        );
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
     * @return \Illuminate\Database\Eloquent\Relations\MorphToMany
     */
    public function morphToMany(
        $related,
        $name,
        $table = null,
        $foreignPivotKey = null,
        $relatedPivotKey = null,
        $parentKey = null,
        $relatedKey = null,
        $relation = null,
        $inverse = false
    ) {
        if ('' === (string)$relation || \str_contains($relation, '{closure}')) {
            $relation = $this->guessBelongsToManyRelation();
        }

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
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Illuminate\Database\Eloquent\Model $parent
     * @param string $name
     * @param string $table
     * @param string $foreignPivotKey
     * @param string $relatedPivotKey
     * @param string $parentKey
     * @param string $relatedKey
     * @param string|null $relationName
     * @param bool $inverse
     * @return \Illuminate\Database\Eloquent\Relations\MorphToMany
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
     * @return \Illuminate\Database\Eloquent\Relations\MorphToMany
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
     * Get the relationship name of the belongsToMany relationship.
     *
     * @return string|null
     */
    protected function guessBelongsToManyRelation()
    {
        $caller = Arr::first(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), function ($trace) {
            return !in_array(
                $trace['function'],
                array_merge(static::$manyMethods, ['guessBelongsToManyRelation'])
            );
        });

        $relation = $caller['function'] ?? null;

        if (
            ('' === (string)$relation || \str_contains($relation, '{closure}'))
            && isset(static::$segregatedRelationNameBeingCalled)
        ) {
            return static::$segregatedRelationNameBeingCalled;
        }

        return $relation;
    }

    /**
     * Get the joining table name for a many-to-many relation.
     *
     * @param string $related
     * @param \Illuminate\Database\Eloquent\Model|null $instance
     * @return string
     */
    public function joiningTable($related, $instance = null)
    {
        // The joining table name, by convention, is simply the snake cased models
        // sorted alphabetically and concatenated with an underscore, so we can
        // just sort the models and join them together to get the table name.
        $segments = [
            $instance ? $instance->joiningTableSegment()
                : Str::snake(class_basename($related)),
            $this->joiningTableSegment(),
        ];

        // Now that we have the model names in an array we can just sort them and
        // use the implode function to join them together with an underscores,
        // which is typically used by convention within the database system.
        sort($segments);

        return strtolower(implode('_', $segments));
    }

    /**
     * Get this model's half of the intermediate table name for belongsToMany relationships.
     *
     * @return string
     */
    public function joiningTableSegment()
    {
        return Str::snake(class_basename($this));
    }

    /**
     * Determine if the model touches a given relation.
     *
     * @param string $relation
     * @return bool
     */
    public function touches($relation)
    {
        return in_array($relation, $this->getTouchedRelations());
    }

    /**
     * Touch the owning relations of the model.
     *
     * @return void
     */
    public function touchOwners()
    {
        foreach ($this->getTouchedRelations() as $relation) {
            $this->callSegregatedRelation($relation)->touch();

            if (($val = $this->getRelationValue($relation)) instanceof self) {
                $val->fireModelEvent('saved', false);

                $val->touchOwners();
            } elseif ($val instanceof Collection) {
                $val->each->touchOwners();
            }
        }
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

    /**
     * Create a new model instance for a related model.
     *
     * @param string $class
     * @return mixed
     */
    protected function newRelatedInstance($class)
    {
        return tap(new $class(), function ($instance) {
            if (!$instance->getConnectionName()) {
                $instance->setConnection($this->connection);
            }
        });
    }

    /**
     * Create a new model instance for a related "through" model.
     *
     * @param string $class
     * @return mixed
     */
    protected function newRelatedThroughInstance($class)
    {
        return new $class();
    }

    /**
     * Get all the loaded relations for the instance.
     *
     * @return array
     */
    public function getRelations()
    {
        return $this->relations;
    }

    /**
     * Get a specified relationship.
     *
     * @param string $relation
     * @return mixed
     */
    public function getRelation($relation)
    {
        return $this->relations[$relation];
    }

    /**
     * Determine if the given relation is loaded.
     *
     * @param string $key
     * @return bool
     */
    public function relationLoaded($key)
    {
        return array_key_exists($key, $this->relations);
    }

    /**
     * Set the given relationship on the model.
     *
     * @param string $relation
     * @param mixed $value
     * @return $this
     */
    public function setRelation($relation, $value)
    {
        $this->relations[$relation] = $value;

        return $this;
    }

    /**
     * Unset a loaded relationship.
     *
     * @param string $relation
     * @return $this
     */
    public function unsetRelation($relation)
    {
        unset($this->relations[$relation]);

        return $this;
    }

    /**
     * Set the entire relations array on the model.
     *
     * @param array $relations
     * @return $this
     */
    public function setRelations(array $relations)
    {
        $this->relations = $relations;

        return $this;
    }

    /**
     * Duplicate the instance and unset all the loaded relations.
     *
     * @return $this
     */
    public function withoutRelations()
    {
        $model = clone $this;

        return $model->unsetRelations();
    }

    /**
     * Unset all the loaded relations for the instance.
     *
     * @return $this
     */
    public function unsetRelations()
    {
        $this->relations = [];

        return $this;
    }

    /**
     * Get the relationships that are touched on save.
     *
     * @return array
     */
    public function getTouchedRelations()
    {
        return $this->touches;
    }

    /**
     * Set the relationships that are touched on save.
     *
     * @param array $touches
     * @return $this
     */
    public function setTouchedRelations(array $touches)
    {
        $this->touches = $touches;

        return $this;
    }

    /**
     * Get the list of all currently identified relationship keys.
     *
     * This list includes:
     * 1. Explicitly defined relations from
     * @see segregatedRelationsDefinitionMap()
     * 2. Implicit method-based relations that have been "promoted" to the global
     * static map via @see resolveSegregatedRelationClosure()
     *
     * @param bool $discoverMethods  If true, performs a one-time SLOW REFLECTION scan to identify and
     * promote all typed relationship methods to the global map.
     *
     * @note This list is usage-dependent when $discoverMethods is false. If true, the static
     * map is force-populated for the remainder of the request lifecycle.
     * THE FASTEST WAY for execution is to refactor all method relations by moving them into that map or
     * manually promote all method relations to segregated relations via:
     * @see segregatedRelationsDefinitionMap()
     *
     * @return string[]
     */
    final public function segregatedRelationList(bool $discoverMethods = false): array
    {
        if ($discoverMethods) {
            $this->promoteMethodRelationsToSegregatedRelations();
        }

        return \array_keys($this->thisSegregatedRelationDefinitionMap());
    }

    /**
     * Final gateway to check if a relation exists in the segregated map.
     */
    final public function isRelationInSegregatedRelationsMap(string $relation, bool $checkMethodExist = true): bool
    {
        if ($checkMethodExist) {
            return (
                $this->resolveSegregatedRelationClosure($relation) ?? $this->relationResolver(static::class, $relation)
            ) instanceof \Closure;
        }

        return (
            (
                self::$segregatedRelationsGlobalMap[static::class][$relation] ??=
                    $this->thisSegregatedRelationDefinitionMap()[$relation] ?? null
            ) ?? $this->relationResolver(static::class, $relation)
        ) instanceof \Closure;
    }

    /**
     * Bind the relation callback to the current instance ($this) and call it.
     *
     * @throws \LogicException
     */
    final public function callSegregatedRelation(string $method, array $parameters = []): Relation
    {
        $previous = self::$segregatedRelationNameBeingCalled;

        try {
            self::$segregatedRelationNameBeingCalled = $method;
            $closure = $this->resolveSegregatedRelationClosure($method);

            if ($closure instanceof \Closure) {
                /**
                 * Late Binding.
                 * We take the shared closure and temporarily bind it to the current instance.
                 * This allows the developer to use $this->hasOne({...}) inside the closure.
                 */
                return $this->validateRelationInstance(
                    $closure->call($this, ...$parameters),
                    $method
                );
            }

            if (($resolver = $this->relationResolver(static::class, $method)) instanceof \Closure) {
                return $this->validateRelationInstance($resolver->call($this, $this), $method);
            }

            throw new \LogicException('Relation "' . $method . '" not defined in ' . static::class);
        } finally {
            self::$segregatedRelationNameBeingCalled = $previous;
        }
    }

    /**
     * Get the ReflectionFunction for a segregated relation to read attributes and types.
     */
    final public function getSegregatedRelationReflectionFunction(string $relation): ?\ReflectionFunction
    {
        $cached = self::$segregatedRelationsReflectionFunctionsMap[static::class][$relation] ?? null;

        if ($cached !== null) {
            return $cached ?: null;
        }

        return (self::$segregatedRelationsReflectionFunctionsMap[static::class][$relation] ??=
            ($closure = $this->resolveSegregatedRelationClosure($relation)) instanceof \Closure ?
                new \ReflectionFunction($closure) :
                false) ?: null;
    }

    /**
     * Override this if you prefer to separate your relations definitions from the model's methods
     * DO NOT USE static closures!
     * There is no check/validation for this structure so make sure you declare it right.
     *
     *  return [
     *      'relName' => fn(): HasOne => $this->hasOne(Model::class, 'model_id', 'id'),
     *      // Reuse the segregated relation inside another segregated relation:
     *      'relNameScoped' => fn(): HasOne => $this->relName()->where('col', '=', 'text'),
     *      'relNameScoped2' => fn(): HasOne => $this->callSegregatedRelation('relName')->where('col', '=', 'text'),
     *      // Reuse the method relation:
     *      'relNameAsMethod' => fn(): HasOne => $this->relNameAsMethod(),
     *      // AVOID THESE:
     *      'relNameAsMethod' => $this->relNameAsMethod(...), // hard-binds closure scope to the first booted model
     *      'relNameAsMethod' => [$this, 'relNameAsMethod'], // is not a Closure
     *      'relNameAsMethod' => fn(): HasOne => [$this, 'relNameAsMethod'](),
     *      // DO NOT USE IT LIKE THIS!:
     *      'relNameAsMethod' => fn(): HasOne => $this->relNameAsMethod(...)(), // executes the relation inside the map.
     *  ];
     *
     */
    protected function segregatedRelationsDefinitionMap(): array
    {
        return [];
    }

    /**
     * @throws \LogicException
     */
    protected function validateRelationInstance(mixed $relation, string $method): Relation
    {
        if ($relation instanceof Relation) {
            return $relation;
        }

        throw new \LogicException(\sprintf(
            null === $relation ?
                '%s::%s must return a relationship instance, but "null" was returned. Was the "return" ' .
                'keyword used?' :
                '%s::%s must return a relationship instance.',
            static::class,
            $method
        ));
    }

    protected static function unbindClosure(\Closure $closure): \Closure
    {
        try {
            return $closure->bindTo(null, static::class) ?? $closure;
        } catch (\Throwable) {
            // Keep original closure if locked by php if inside a nonstatic closure already (db transaction closure)
            return $closure;
        }
    }

    private function thisSegregatedRelationDefinitionMap(): array
    {
        return self::$segregatedRelationsGlobalMap[static::class] ??=
            \array_map(
                // definitions are bound to $this so we detach from scope to save memory
                fn(\Closure $closure): \Closure => static::unbindClosure($closure),
                $this->segregatedRelationsDefinitionMap()
            );
    }

    private function resolveSegregatedRelationClosure(string $relation): ?\Closure
    {
        $cached = self::$segregatedRelationsGlobalMap[static::class][$relation] ?? null;

        if ($cached !== null) {
            return $cached ?: null;
        }

        return (self::$segregatedRelationsGlobalMap[static::class][$relation] ??=
            ($this->thisSegregatedRelationDefinitionMap()[$relation] ?? (
                !$this->hasAttributeMutator($relation)
                && !(
                    \str_ends_with($relation, 'Attribute')
                    && (\str_starts_with($relation, 'set') || !\str_starts_with($relation, 'get'))
                )
                && !\method_exists($this instanceof Model ? Model::class : self::class, $relation)
                && \in_array(
                    $relation,
                    (static fn($model): array => \get_class_methods($model) ?? [])->bindTo(null, null)($this) ?? [],
                    true
                ) ? static::unbindClosure(fn(...$args): mixed => $this->$relation(...$args)) : false
            ))) ?: null;
    }

    /**
     * Force-populates the Global Map with all detectable relations.
     * This is SLOW as it uses Reflection to ensure the map is exhaustive.
     */
    private function promoteMethodRelationsToSegregatedRelations(): void
    {
        $this->thisSegregatedRelationDefinitionMap();
        $reflection = new \ReflectionClass($this);
        $objectOrClassExcludedMethods = $this instanceof Model ? Model::class : self::class;

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $name = $method->getName();

            if (
                isset(self::$segregatedRelationsGlobalMap[static::class][$name])
                || \method_exists($objectOrClassExcludedMethods, $name)
            ) {
                continue;
            }

            $returnType = $method->getReturnType();

            foreach (
                $returnType instanceof \ReflectionUnionType
                    ? $returnType->getTypes()
                    : [$returnType] as $type
            ) {
                if ($type instanceof \ReflectionNamedType && \is_subclass_of($type->getName(), Relation::class)) {
                    self::$segregatedRelationsGlobalMap[static::class][$name] = static::unbindClosure(
                        fn(...$args): mixed => $this->$name(...$args)
                    );

                    break;
                }
            }
        }
    }
}
