<?php

namespace MacropaySolutions\Kernel\Database\Obvious\Concerns;

use MacropaySolutions\Kernel\Database\Obvious\Collection;
use MacropaySolutions\Kernel\Database\Obvious\Model;
use MacropaySolutions\Kernel\Database\Obvious\Relations\BelongsTo;
use MacropaySolutions\Kernel\Database\Obvious\Relations\BelongsToMany;
use MacropaySolutions\Kernel\Database\Obvious\Relations\HasMany;
use MacropaySolutions\Kernel\Database\Obvious\Relations\HasManyThrough;
use MacropaySolutions\Kernel\Database\Obvious\Relations\HasOne;
use MacropaySolutions\Kernel\Database\Obvious\Relations\HasOneThrough;
use MacropaySolutions\Kernel\Database\Obvious\Relations\Relation;

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
     */
    protected array $relations = [];

    /**
     * The relationships that should be touched on save.
     * Using this will slow down the execution!
     */
    protected array $touches = [];

    public static function getSegregatedRelationNameBeingCalled(): ?string
    {
        return self::$segregatedRelationNameBeingCalled;
    }

    /**
     * Define a one-to-one relationship.
     */
    protected function hasOne(string $related, string $foreignKey, string $localKey): HasOne
    {
        $instance = $this->newRelatedInstance($related);

        return \di(HasOne::class, [$instance->newQuery(), $this, $instance->getTable() . '.' . $foreignKey, $localKey]);
    }

    /**
     * Define a has-one-through relationship.
     */
    protected function hasOneThrough(
        string $related,
        string $through,
        string $firstKey,
        string $secondKey,
        string $localKey,
        string $secondLocalKey
    ): HasOneThrough {
        return \di(HasOneThrough::class, [
            $this->newRelatedInstance($related)->newQuery(),
            $this,
            new $through(),
            $firstKey,
            $secondKey,
            $localKey,
            $secondLocalKey,
       ]);
    }

    /**
     * Define an inverse one-to-one or many relationship.
     * $relation is auto-handled by the segregated relations logic.
     * If you are defining and using this relation as a model method, it is needed for associate and dissociate methods
     */
    protected function belongsTo(
        string $related,
        string $foreignKey,
        string $ownerKey,
        ?string $relation = null
    ): BelongsTo {
        return \di(BelongsTo::class, [
            $this->newRelatedInstance($related)->newQuery(),
            $this,
            $foreignKey,
            $ownerKey,
            $relation ?? static::$segregatedRelationNameBeingCalled
        ]);
    }

    /**
     * Define a one-to-many relationship.
     */
    protected function hasMany(string $related, string $foreignKey, string $localKey): HasMany
    {
        $instance = $this->newRelatedInstance($related);

        return \di(HasMany::class, [
            $instance->newQuery(),
            $this,
            $instance->getTable() . '.' . $foreignKey,
            $localKey
        ]);
    }

    /**
     * Define a has-many-through relationship.
     */
    protected function hasManyThrough(
        string $related,
        string $through,
        string $firstKey,
        string $secondKey,
        string $localKey,
        string $secondLocalKey
    ): HasManyThrough {
        return \di(HasManyThrough::class, [
            $this->newRelatedInstance($related)->newQuery(),
            $this,
            new $through(),
            $firstKey,
            $secondKey,
            $localKey,
            $secondLocalKey
        ]);
    }

    /**
     * Define a many-to-many relationship.
     * @param string|class-string<\MacropaySolutions\Kernel\Database\Obvious\Model> $table
     */
    protected function belongsToMany(
        string $related,
        string $table,
        string $foreignPivotKey,
        string $relatedPivotKey,
        string $parentKey,
        string $relatedKey,
        ?string $relation = null
    ): BelongsToMany {
        return \di(BelongsToMany::class, [
            $this->newRelatedInstance($related)->newQuery(),
            $this,
            $table,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey,
            $relatedKey,
            $relation ?? static::$segregatedRelationNameBeingCalled
        ]);
    }

    /**
     * Determine if the model touches a given relation.
     */
    public function touches(string $relation): bool
    {
        return \in_array($relation, $this->getTouchedRelations(), true);
    }

    /**
     * Touch the owning relations of the model.
     */
    public function touchOwners(): void
    {
        foreach ($this->getTouchedRelations() as $relation) {
            $this->callSegregatedRelation($relation)->touch();

            if (($val = $this->getRelationValue($relation)) instanceof self) {
                $val->fireModelEvent('saved', false);

                $val->touchOwners();

                return;
            }

            if ($val instanceof Collection) {
                $val->each->touchOwners();
            }
        }
    }

    /**
     * Create a new model instance for a related model.
     */
    protected function newRelatedInstance(string $class): Model
    {
        return tap(new $class(), function ($instance) {
            if (!$instance->getConnectionName()) {
                $instance->setConnection($this->connection);
            }
        });
    }

    /**
     * Get all the loaded relations for the instance.
     */
    public function getRelations(): array
    {
        return $this->relations;
    }

    /**
     * Get a specified relationship.
     */
    public function getRelation(string $relation): null|Model|Collection
    {
        return $this->relations[$relation];
    }

    /**
     * Determine if the given relation is loaded.
     */
    public function relationLoaded(string $key): bool
    {
        return \array_key_exists($key, $this->relations);
    }

    /**
     * Set the given relationship on the model.
     */
    public function setRelation(string $relation, null|Model|Collection $value): static
    {
        $this->relations[$relation] = $value;

        return $this;
    }

    /**
     * Unset a loaded relationship.
     */
    public function unsetRelation(string $relation): static
    {
        unset($this->relations[$relation]);

        return $this;
    }

    /**
     * Set the entire relations array on the model.
     */
    public function setRelations(array $relations): static
    {
        $this->relations = $relations;

        return $this;
    }

    /**
     * Duplicate the instance and unset all the loaded relations.
     */
    public function withoutRelations(): static
    {
        return (clone $this)->unsetRelations();
    }

    /**
     * Unset all the loaded relations for the instance.
     */
    public function unsetRelations(): static
    {
        $this->relations = [];

        return $this;
    }

    /**
     * Get the relationships that are touched on save.
     */
    public function getTouchedRelations(): array
    {
        return $this->touches;
    }

    /**
     * Set the relationships that are touched on save.
     */
    public function setTouchedRelations(array $touches): static
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
            return $this->resolveSegregatedRelationClosure($relation) instanceof \Closure;
        }

        return (
            self::$segregatedRelationsGlobalMap[static::class][$relation] ??=
                $this->thisSegregatedRelationDefinitionMap()[$relation] ?? null
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
                !(
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
