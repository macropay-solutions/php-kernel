<?php

namespace MacropaySolutions\Kernel\Database\Obvious\Relations;

use BadMethodCallException;
use MacropaySolutions\Kernel\Database\Obvious\Builder;
use MacropaySolutions\Kernel\Database\Obvious\Collection;
use MacropaySolutions\Kernel\Database\Obvious\Model;
use MacropaySolutions\Kernel\Database\Obvious\Relations\Concerns\InteractsWithDictionary;

class MorphTo extends BelongsTo
{
    use InteractsWithDictionary;

    /**
     * The type of the polymorphic relation.
     *
     * @var string
     */
    protected $morphType;

    /**
     * The models whose relations are being eager loaded.
     *
     * @var \MacropaySolutions\Kernel\Database\Obvious\Collection
     */
    protected $models;

    /**
     * All the models keyed by ID.
     *
     * @var array
     */
    protected $dictionary = [];

    /**
     * A buffer of dynamic calls to query macros.
     *
     * @var array
     */
    protected $macroBuffer = [];

    /**
     * A map of relations to load for each individual morph type.
     *
     * @var array
     */
    protected $morphableEagerLoads = [];

    /**
     * A map of relationship counts to load for each individual morph type.
     *
     * @var array
     */
    protected $morphableEagerLoadCounts = [];

    /**
     * A map of constraints to apply for each individual morph type.
     *
     * @var array
     */
    protected $morphableConstraints = [];

    /**
     * Create a new morph to relationship instance.
     *
     * @param \MacropaySolutions\Kernel\Database\Obvious\Builder $query
     * @param \MacropaySolutions\Kernel\Database\Obvious\Model $parent
     * @param string $foreignKey
     * @param string $ownerKey
     * @param string $type
     * @param string $relation
     * @return void
     */
    public function __construct(Builder $query, Model $parent, $foreignKey, $ownerKey, $type, $relation)
    {
        $this->morphType = $type;

        parent::__construct($query, $parent, $foreignKey, $ownerKey, $relation);
    }

    /**
     * Set the constraints for an eager load of the relation.
     *
     * @param array $models
     * @return void
     */
    public function addEagerConstraints(array $models)
    {
        $this->buildDictionary($this->models = Collection::make($models));
    }

    /**
     * Build a dictionary with the models.
     *
     * @param \MacropaySolutions\Kernel\Database\Obvious\Collection $models
     * @return void
     */
    protected function buildDictionary(Collection $models)
    {
        foreach ($models as $model) {
            if ($model->{$this->morphType}) {
                $morphTypeKey = $this->getDictionaryKey($model->{$this->morphType});
                $foreignKeyKey = $this->getDictionaryKey($model->{$this->foreignKey});

                $this->dictionary[$morphTypeKey][$foreignKeyKey][] = $model;
            }
        }
    }

    /**
     * Get the results of the relationship.
     *
     * Called via eager load method of Obvious query builder.
     *
     * @return mixed
     */
    public function getEager()
    {
        foreach (array_keys($this->dictionary) as $type) {
            $this->matchToMorphParents($type, $this->getResultsByType($type));
        }

        return $this->models;
    }

    /**
     * Get all the relation results for a type.
     *
     * @param string $type
     * @return \MacropaySolutions\Kernel\Database\Obvious\Collection
     */
    protected function getResultsByType($type)
    {
        $instance = $this->createModelByType($type);

        $ownerKey = $this->ownerKey ?? $instance->getKeyName();

        $query = $this->replayMacros($instance->newQuery())
            ->mergeConstraintsFrom($this->getQuery())
            ->with(
                array_merge(
                    $this->getQuery()->getEagerLoads(),
                    (array)($this->morphableEagerLoads[get_class($instance)] ?? [])
                )
            )
            ->withCount(
                (array)($this->morphableEagerLoadCounts[get_class($instance)] ?? [])
            );

        if ($callback = ($this->morphableConstraints[get_class($instance)] ?? null)) {
            $callback($query);
        }

        $whereIn = $this->whereInMethod($instance, $ownerKey);

        return $query->{$whereIn}(
            $instance->getTable() . '.' . $ownerKey,
            $this->gatherKeysByType($type, $instance->getKeyType())
        )->get();
    }

    /**
     * Gather all the foreign keys for a given type.
     *
     * @param string $type
     * @param string $keyType
     * @return array
     */
    protected function gatherKeysByType($type, $keyType)
    {
        return $keyType !== 'string'
            ? array_keys($this->dictionary[$type])
            : array_map(function ($modelId) {
                return (string)$modelId;
            }, array_filter(array_keys($this->dictionary[$type])));
    }

    /**
     * Create a new model instance by type.
     *
     * @param string $type
     * @return \MacropaySolutions\Kernel\Database\Obvious\Model
     */
    public function createModelByType($type)
    {
        $class = Model::getActualClassNameForMorph($type);

        return tap(new $class(), function ($instance) {
            if (!$instance->getConnectionName()) {
                $instance->setConnection($this->getConnection()->getName());
            }
        });
    }

    /**
     * Match the eagerly loaded results to their parents.
     *
     * @param array $models
     * @param \MacropaySolutions\Kernel\Database\Obvious\Collection $results
     * @param string $relation
     * @return array
     */
    public function match(array $models, Collection $results, $relation)
    {
        return $models;
    }

    /**
     * Match the results for a given type to their parents.
     *
     * @param string $type
     * @param \MacropaySolutions\Kernel\Database\Obvious\Collection $results
     * @return void
     */
    protected function matchToMorphParents($type, Collection $results)
    {
        foreach ($results as $result) {
            $ownerKey = !is_null($this->ownerKey) ? $this->getDictionaryKey(
                $result->{$this->ownerKey}
            ) : $result->getKey();

            if (isset($this->dictionary[$type][$ownerKey])) {
                foreach ($this->dictionary[$type][$ownerKey] as $model) {
                    $model->setRelation($this->relationName, $result);
                }
            }
        }
    }

    /**
     * Associate the model instance to the given parent.
     *
     * @param \MacropaySolutions\Kernel\Database\Obvious\Model|null $model
     * @return \MacropaySolutions\Kernel\Database\Obvious\Model
     */
    public function associate(Model|int|string|null $model): Model
    {
        if (\is_int($model) || \is_string($model)) {
            throw new \InvalidArgumentException('MorphTo::associate() requires a Model instance or null. ' .
                'Raw IDs cannot be used because the morph type is unknown.');
        }

        if ($model instanceof Model) {
            if ('' === (string)$this->relationName) {
                throw new \RuntimeException('RelationName not defined when associating model ' . $model::class .
                    ' to ' . $this->getModel()::class);
            }

            $foreignKey = $this->ownerKey && $model->{$this->ownerKey}
                ? $this->ownerKey
                : $model->getKeyName();
        }

        if ('' === (string)$this->relationName) {
            throw new \RuntimeException('RelationName not defined when associating model {' . $model .
                '} to ' . $this->getModel()::class);
        }

        $this->parent->setAttribute(
            $this->foreignKey,
            $model instanceof Model ? $model->{$foreignKey} : null
        );

        $this->parent->setAttribute(
            $this->morphType,
            $model instanceof Model ? $model->getMorphClass() : null
        );

        return $this->parent->setRelation($this->relationName, $model instanceof Model ? $model: null);
    }

    /**
     * Dissociate previously associated model from the given parent.
     */
    public function dissociate(): Model
    {
        $this->parent->setAttribute($this->foreignKey, null);

        $this->parent->setAttribute($this->morphType, null);

        return '' !== (string)$this->relationName ?
            $this->parent->setRelation($this->relationName, null) :
            $this->parent;
    }

    /**
     * Touch all the related models for the relationship.
     */
    public function touch(): void
    {
        if (!is_null($this->child->{$this->foreignKey})) {
            parent::touch();
        }
    }

    /**
     * Make a new related instance for the given model.
     *
     * @param \MacropaySolutions\Kernel\Database\Obvious\Model $parent
     * @return \MacropaySolutions\Kernel\Database\Obvious\Model
     */
    protected function newRelatedInstanceFor(Model $parent)
    {
        return $parent->callSegregatedRelation($this->getRelationName())->getRelated()->newInstance();
    }

    /**
     * Get the foreign key "type" name.
     *
     * @return string
     */
    public function getMorphType()
    {
        return $this->morphType;
    }

    /**
     * Get the dictionary used by the relationship.
     *
     * @return array
     */
    public function getDictionary()
    {
        return $this->dictionary;
    }

    /**
     * Specify which relations to load for a given morph type.
     *
     * @param array $with
     * @return \MacropaySolutions\Kernel\Database\Obvious\Relations\MorphTo
     */
    public function morphWith(array $with)
    {
        $this->morphableEagerLoads = array_merge(
            $this->morphableEagerLoads,
            $with
        );

        return $this;
    }

    /**
     * Specify which relationship counts to load for a given morph type.
     *
     * @param array $withCount
     * @return \MacropaySolutions\Kernel\Database\Obvious\Relations\MorphTo
     */
    public function morphWithCount(array $withCount)
    {
        $this->morphableEagerLoadCounts = array_merge(
            $this->morphableEagerLoadCounts,
            $withCount
        );

        return $this;
    }

    /**
     * Specify constraints on the query for a given morph type.
     *
     * @param array $callbacks
     * @return \MacropaySolutions\Kernel\Database\Obvious\Relations\MorphTo
     */
    public function constrain(array $callbacks)
    {
        $this->morphableConstraints = array_merge(
            $this->morphableConstraints,
            $callbacks
        );

        return $this;
    }

    /**
     * Indicate that soft deleted models should be included in the results.
     *
     * @return $this
     */
    public function withTrashed()
    {
        $callback = fn($query) => $query->hasMacro('withTrashed') ? $query->withTrashed() : $query;

        $this->macroBuffer[] = [
            'method' => 'when',
            'parameters' => [true, $callback],
        ];

        return $this->when(true, $callback);
    }

    /**
     * Indicate that soft deleted models should not be included in the results.
     *
     * @return $this
     */
    public function withoutTrashed()
    {
        $callback = fn($query) => $query->hasMacro('withoutTrashed') ? $query->withoutTrashed() : $query;

        $this->macroBuffer[] = [
            'method' => 'when',
            'parameters' => [true, $callback],
        ];

        return $this->when(true, $callback);
    }

    /**
     * Indicate that only soft deleted models should be included in the results.
     *
     * @return $this
     */
    public function onlyTrashed()
    {
        $callback = fn($query) => $query->hasMacro('onlyTrashed') ? $query->onlyTrashed() : $query;

        $this->macroBuffer[] = [
            'method' => 'when',
            'parameters' => [true, $callback],
        ];

        return $this->when(true, $callback);
    }

    /**
     * Replay stored macro calls on the actual related instance.
     *
     * @param \MacropaySolutions\Kernel\Database\Obvious\Builder $query
     * @return \MacropaySolutions\Kernel\Database\Obvious\Builder
     */
    protected function replayMacros(Builder $query)
    {
        foreach ($this->macroBuffer as $macro) {
            $query->{$macro['method']}(...$macro['parameters']);
        }

        return $query;
    }

    /**
     * Handle dynamic method calls to the relationship.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        try {
            $result = parent::__call($method, $parameters);

            if (in_array($method, ['select', 'selectRaw', 'selectSub', 'addSelect', 'withoutGlobalScopes'])) {
                $this->macroBuffer[] = compact('method', 'parameters');
            }

            return $result;
        } catch (BadMethodCallException) {
            // If we tried to call a method that does not exist on the parent Builder instance,
            // we'll assume that we want to call a query macro (e.g. withTrashed) that only
            // exists on related models. We will just store the call and replay it later.
            $this->macroBuffer[] = compact('method', 'parameters');

            return $this;
        }
    }
}
