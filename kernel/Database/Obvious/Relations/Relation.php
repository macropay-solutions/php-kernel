<?php

namespace MacropaySolutions\Kernel\Database\Obvious\Relations;

use MacropaySolutions\Kernel\Contracts\Database\Obvious\Builder as BuilderContract;
use MacropaySolutions\Kernel\Database\MultipleRecordsFoundException;
use MacropaySolutions\Kernel\Database\Obvious\Builder;
use MacropaySolutions\Kernel\Database\Obvious\Collection;
use MacropaySolutions\Kernel\Database\Obvious\Model;
use MacropaySolutions\Kernel\Database\Obvious\ModelNotFoundException;
use MacropaySolutions\Kernel\Database\Query\Expression;
use MacropaySolutions\Kernel\Support\Traits\ForwardsCalls;
use MacropaySolutions\Kernel\Support\Traits\Macroable;

abstract class Relation implements BuilderContract
{
    use ForwardsCalls;
    use Macroable {
        Macroable::__call as macroCall;
    }

    /**
     * The Obvious query builder instance.
     *
     * @var \MacropaySolutions\Kernel\Database\Obvious\Builder
     */
    protected $query;

    /**
     * The parent model instance.
     *
     * @var \MacropaySolutions\Kernel\Database\Obvious\Model
     */
    protected $parent;

    /**
     * The related model instance.
     *
     * @var \MacropaySolutions\Kernel\Database\Obvious\Model
     */
    protected $related;

    /**
     * Indicates whether the eagerly loaded relation should implicitly return an empty collection.
     *
     * @var bool
     */
    protected $eagerKeysWereEmpty = false;

    /**
     * Indicates if the relation is adding constraints.
     *
     * @var bool
     */
    protected static $constraints = true;

    /**
     * The count of self joins.
     *
     * @var int
     */
    protected static $selfJoinCount = 0;

    protected static ?string $noConstraintsForRelationName = null;

    /**
     * Create a new relation instance.
     *
     * @param \MacropaySolutions\Kernel\Database\Obvious\Builder $query
     * @param \MacropaySolutions\Kernel\Database\Obvious\Model $parent
     * @param \MacropaySolutions\Kernel\Database\Obvious\Model|null $resourceModel
     * @return void
     */
    public function __construct(Builder $query, Model $parent, ?Model $resourceModel = null)
    {
        $this->query = $query;
        $this->parent = $parent;
        $this->related = $query->getModel();
        $resourceModel ??= $parent;

        if (
            '' !== (string)static::$noConstraintsForRelationName
            || '' !== (string)$resourceModel->nowEagerLoadingRelationNameWithNoConstraints
        ) {
            /**
             *   1st execution is for ExampleModel $exampleModel on 'rel' relation
             * with nowEagerLoadingRelationNameWithNoConstraints = 'rel'
             *           and with $noConstraintsForRelationName = 'rel'
             */
            /*   2nd execution is for UserModel $userModel on 'categories' relation
                with nowEagerLoadingRelationNameWithNoConstraints = null
                         and with $noConstraintsForRelationName = 'rel' */


            /*    1st execution is for ExampleModel $exampleModel on 'children' relation
                with nowEagerLoadingRelationNameWithNoConstraints = null
                          and with $noConstraintsForRelationName = 'rel' */
            /**
             *   2nd execution is for ExampleModel $exampleModel on 'rel' relation
             * with nowEagerLoadingRelationNameWithNoConstraints = 'rel'
             *           and with $noConstraintsForRelationName = 'rel'
             */
            /* 3rd execution is for UserModel $userModel on 'categories' relation
                with nowEagerLoadingRelationNameWithNoConstraints = null
                   and with $noConstraintsForRelationName = 'rel' */
            static::$constraints =
                static::$noConstraintsForRelationName !== $resourceModel->nowEagerLoadingRelationNameWithNoConstraints;
        }

        $this->addConstraints();
    }

    /**
     * Run a callback with constraints disabled on the relation.
     *
     * @return mixed
     */
    public static function noConstraints(\Closure $callback, string $relationName)
    {
        $previous = static::$constraints;
        $previousNoConstraintsForRelationName = static::$noConstraintsForRelationName;
        static::$noConstraintsForRelationName = $relationName;

        try {
            return $callback();
        } finally {
            static::$constraints = $previous;
            static::$noConstraintsForRelationName = $previousNoConstraintsForRelationName;
        }
    }

    /**
     * Set the base constraints on the relation query.
     *
     * @return void
     */
    abstract public function addConstraints();

    /**
     * Set the constraints for an eager load of the relation.
     *
     * @param array $models
     * @return void
     */
    abstract public function addEagerConstraints(array $models);

    /**
     * Initialize the relation on a set of models.
     *
     * @param array $models
     * @param string $relation
     * @return array
     */
    abstract public function initRelation(array $models, $relation);

    /**
     * Match the eagerly loaded results to their parents.
     *
     * @param array $models
     * @param \MacropaySolutions\Kernel\Database\Obvious\Collection $results
     * @param string $relation
     * @return array
     */
    abstract public function match(array $models, Collection $results, $relation);

    /**
     * Get the results of the relationship.
     *
     * @return mixed
     */
    abstract public function getResults();

    /**
     * Get the relationship for eager loading.
     *
     * @return \MacropaySolutions\Kernel\Database\Obvious\Collection
     */
    public function getEager()
    {
        return $this->eagerKeysWereEmpty
            ? $this->query->getModel()->newCollection()
            : $this->get();
    }

    /**
     * Execute the query and get the first result if it's the sole matching record.
     *
     * @param array|string $columns
     * @return \MacropaySolutions\Kernel\Database\Obvious\Model
     *
     * @throws \MacropaySolutions\Kernel\Database\Obvious\ModelNotFoundException<\MacropaySolutions\Kernel\Database\Obvious\Model>
     * @throws \MacropaySolutions\Kernel\Database\MultipleRecordsFoundException
     */
    public function sole($columns = ['*'])
    {
        $result = $this->take(2)->get($columns);

        $count = $result->count();

        if ($count === 0) {
            throw (new ModelNotFoundException())->setModel(get_class($this->related));
        }

        if ($count > 1) {
            throw new MultipleRecordsFoundException($count);
        }

        return $result->first();
    }

    /**
     * Execute the query as a "select" statement.
     *
     * @param array $columns
     * @return \MacropaySolutions\Kernel\Database\Obvious\Collection
     */
    public function get($columns = ['*'])
    {
        return $this->query->get($columns);
    }

    /**
     * Touch all the related models for the relationship.
     */
    public function touch(): void
    {
        $model = $this->getRelated();

        if (!$model::isIgnoringTouch()) {
            $this->rawUpdate([
                $model->getUpdatedAtColumn() => \date($model::UPDATED_AT_FORMAT),
            ]);
        }
    }

    /**
     * Run a raw update against the base query.
     *
     * @param array $attributes
     * @return int
     */
    public function rawUpdate(array $attributes = [])
    {
        return $this->query->withoutGlobalScopes()->update($attributes);
    }

    /**
     * Add the constraints for a relationship count query.
     *
     * @param \MacropaySolutions\Kernel\Database\Obvious\Builder $query
     * @param \MacropaySolutions\Kernel\Database\Obvious\Builder $parentQuery
     * @return \MacropaySolutions\Kernel\Database\Obvious\Builder
     */
    public function getRelationExistenceCountQuery(Builder $query, Builder $parentQuery)
    {
        return $this->getRelationExistenceQuery(
            $query,
            $parentQuery,
            new Expression('count(*)')
        )->setBindings([], 'select');
    }

    /**
     * Add the constraints for an internal relationship existence query.
     *
     * Essentially, these queries compare on column names like whereColumn.
     *
     * @param \MacropaySolutions\Kernel\Database\Obvious\Builder $query
     * @param \MacropaySolutions\Kernel\Database\Obvious\Builder $parentQuery
     * @param array|mixed $columns
     * @return \MacropaySolutions\Kernel\Database\Obvious\Builder
     */
    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        return $query->select($columns)->whereColumn(
            $this->getQualifiedParentKeyName(),
            '=',
            $this->getExistenceCompareKey()
        );
    }

    /**
     * Get a relationship join table hash.
     *
     * @param bool $incrementJoinCount
     * @return string
     */
    public function getRelationCountHash($incrementJoinCount = true)
    {
        return 'framework_reserved_' . ($incrementJoinCount ? static::$selfJoinCount++ : static::$selfJoinCount);
    }

    /**
     * Get all the primary keys for an array of models.
     *
     * @param array $models
     * @param string|null $key
     * @return array
     */
    protected function getKeys(array $models, $key = null)
    {
        return collect($models)->map(function ($value) use ($key) {
            return $key ? $value->getAttribute($key) : $value->getKey();
        })->values()->unique(null, true)->sort()->all();
    }

    /**
     * Get the query builder that will contain the relationship constraints.
     *
     * @return \MacropaySolutions\Kernel\Database\Obvious\Builder
     */
    protected function getRelationQuery()
    {
        return $this->query;
    }

    /**
     * Get the underlying query for the relation.
     *
     * @return \MacropaySolutions\Kernel\Database\Obvious\Builder
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * Get the base query builder driving the Obvious builder.
     *
     * @return \MacropaySolutions\Kernel\Database\Query\Builder
     */
    public function getBaseQuery()
    {
        return $this->query->getQuery();
    }

    /**
     * Get a base query builder instance.
     *
     * @return \MacropaySolutions\Kernel\Database\Query\Builder
     */
    public function toBase()
    {
        return $this->query->toBase();
    }

    /**
     * Get the parent model of the relation.
     *
     * @return \MacropaySolutions\Kernel\Database\Obvious\Model
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Get the fully qualified parent key name.
     *
     * @return string
     */
    public function getQualifiedParentKeyName()
    {
        return $this->parent->getQualifiedKeyName();
    }

    /**
     * Get the related model of the relation.
     *
     * @return \MacropaySolutions\Kernel\Database\Obvious\Model
     */
    public function getRelated()
    {
        return $this->related;
    }

    /**
     * Get the name of the "created at" column.
     *
     * @return string
     */
    public function createdAt()
    {
        return $this->parent->getCreatedAtColumn();
    }

    /**
     * Get the name of the "updated at" column.
     *
     * @return string
     */
    public function updatedAt()
    {
        return $this->parent->getUpdatedAtColumn();
    }

    /**
     * Get the name of the related model's "updated at" column.
     *
     * @return string
     */
    public function relatedUpdatedAt()
    {
        return $this->related->getUpdatedAtColumn();
    }

    /**
     * Add a whereIn eager constraint for the given set of model keys to be loaded.
     *
     * @param string $whereIn
     * @param string $key
     * @param array $modelKeys
     * @param \MacropaySolutions\Kernel\Database\Obvious\Builder $query
     * @return void
     */
    protected function whereInEager(string $whereIn, string $key, array $modelKeys, $query = null)
    {
        ($query ?? $this->query)->{$whereIn}($key, $modelKeys);

        if ($modelKeys === []) {
            $this->eagerKeysWereEmpty = true;
        }
    }

    /**
     * Get the name of the "where in" method for eager loading.
     *
     * @param \MacropaySolutions\Kernel\Database\Obvious\Model $model
     * @param string $key
     * @return string
     */
    protected function whereInMethod(Model $model, $key)
    {
        return $model->getKeyName() === last(explode('.', $key))
        && in_array($model->getKeyType(), ['int', 'integer'])
            ? 'whereIntegerInRaw'
            : 'whereIn';
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
        if (static::hasMacro($method)) {
            return $this->macroCall($method, $parameters);
        }

        return $this->forwardDecoratedCallTo($this->query, $method, $parameters);
    }

    /**
     * Force a clone of the underlying query builder when cloning.
     *
     * @return void
     */
    public function __clone()
    {
        $this->query = clone $this->query;
    }
}
