<?php

namespace MacropaySolutions\Kernel\Database\Obvious;

use ArrayAccess;
use JsonException;
use JsonSerializable;
use MacropaySolutions\Kernel\Container\Container;
use MacropaySolutions\Kernel\Contracts\Broadcasting\HasBroadcastChannel;
use MacropaySolutions\Kernel\Contracts\Queue\QueueableCollection;
use MacropaySolutions\Kernel\Contracts\Queue\QueueableEntity;
use MacropaySolutions\Kernel\Contracts\Support\Arrayable;
use MacropaySolutions\Kernel\Contracts\Support\CanBeEscapedWhenCastToString;
use MacropaySolutions\Kernel\Contracts\Support\Jsonable;
use MacropaySolutions\Kernel\Database\Connection;
use MacropaySolutions\Kernel\Database\ConnectionInterface;
use MacropaySolutions\Kernel\Database\ConnectionResolverInterface as Resolver;
use MacropaySolutions\Kernel\Database\Obvious\Collection as ObviousCollection;
use MacropaySolutions\Kernel\Database\Obvious\Relations\Concerns\AsPivot;
use MacropaySolutions\Kernel\Database\Obvious\Relations\Pivot;
use MacropaySolutions\Kernel\Database\Obvious\Relations\Relation;
use MacropaySolutions\Kernel\Database\Query\Builder as QueryBuilder;
use MacropaySolutions\Kernel\Support\Arr;
use MacropaySolutions\Kernel\Support\Collection as BaseCollection;
use MacropaySolutions\Kernel\Support\Str;

/**
 * @property-read ?object a manages attributes only. DO NOT STORE THIS IN EXTERNAL VARIABLES!
 * @property-read ?object r manages relations only. DO NOT STORE THIS IN EXTERNAL VARIABLES!
 *  DO NOT ADD PROPERTY HOOKS IN THIS CLASS TO ALLOW OBJECT RECONSTRUCTION AFTER DESERIALIZATION!
 *  only a and r can be declared with get property hook (without set!) to avoid the __get method call
 */
abstract class Model implements
    Arrayable,
    ArrayAccess,
    CanBeEscapedWhenCastToString,
    HasBroadcastChannel,
    Jsonable,
    JsonSerializable,
    QueueableEntity
{
    use Concerns\HasAttributes;
    use Concerns\HasEvents;
    use Concerns\HasGlobalScopes;
    use Concerns\HasRelationships;
    use Concerns\HasTimestamps;
    use Concerns\HasUniqueIds;
    use Concerns\HidesAttributes;
    use Concerns\GuardsAttributes;

    protected const IGNORE_ON_SERIALIZE = [
        'relations',
        'A',
        'R',
        'a',
        'r',
        'tmpDirty',
        'tmpOriginalBeforeAfterEvents',
        'nowEagerLoadingRelationNameWithNoConstraints'
    ];

    /**
     * The connection name for the model.
     */
    protected ?string $connection = null;

    /**
     * The table associated with the model.
     */
    protected string $table;

    /**
     * The primary key for the model.
     */
    protected string $primaryKey = 'id';

    /**
     * The "type" of the primary key ID.
     */
    protected string $keyType = 'int';

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public bool $incrementing = true;

    /**
     * The relations to eager load on every query.
     */
    protected array $with = [];

    /**
     * The relationship counts that should be eager loaded on every query.
     */
    protected array $withCount = [];

    /**
     * Indicates whether lazy loading will be prevented on this model.
     */
    public bool $preventsLazyLoading = false;

    /**
     * The number of models to return for pagination.
     */
    protected int $perPage = 10;

    /**
     * Indicates if the model exists.
     */
    public bool $exists = false;

    /**
     * Indicates if the model was inserted during the object's lifecycle.
     */
    public bool $wasRecentlyCreated = false;

    /**
     * Indicates that the object's string representation should be escaped when __toString is invoked.
     */
    protected bool $escapeWhenCastingToString = false;

    /**
     * The connection resolver instance.
     */
    private static ?Resolver $resolver = null;

    /**
     * The array of booted models.
     */
    protected static array $booted = [];

    /**
     * The array of trait initializers that will be called on each new instance.
     */
    protected static array $traitInitializers = [];

    /**
     * The array of global scopes on the model.
     */
    protected static array $globalScopes = [];

    /**
     * The list of models classes that should not be affected with touch.
     */
    protected static array $ignoreOnTouch = [];

    /**
     * Indicates whether lazy loading should be restricted on all models.
     */
    protected static bool $modelsShouldPreventLazyLoading = false;

    /**
     * Override it in your model/baseModel instead of dynamically changing it to false!
     * When overridden with false, the extra traits that you might use must be manually booted and initialized
     *  in the boot method
     * @see static::bootTraits()
     */
    protected static bool $modelShouldAutoDiscoverAndBootTraits = true;

    /**
     * The callback that is responsible for handling lazy loading violations.
     */
    protected static ?\Closure $lazyLoadingViolationCallback;

    /**
     * Indicates if an exception should be thrown instead of silently discarding non-fillable attributes.
     */
    protected static bool $modelsShouldPreventSilentlyDiscardingAttributes = false;

    /**
     * The callback that is responsible for handling discarded attribute violations.
     */
    protected static ?\Closure $discardedAttributeViolationCallback;

    /**
     * Indicates if an exception should be thrown when trying to access a missing attribute on a retrieved model.
     */
    protected static bool $modelsShouldPreventAccessingMissingAttributes = false;

    /**
     * The callback that is responsible for handling missing attribute violations.
     */
    protected static ?\Closure $missingAttributeViolationCallback;

    /**
     * Indicates if broadcasting is currently enabled.
     */
    protected static bool $isBroadcasting = true;

    /**
     * The name of the "created at" column.
     *
     * @var string|null
     */
    public const CREATED_AT = 'created_at';

    /**
     * The name of the "updated at" column.
     *
     * @var string|null
     */
    public const UPDATED_AT = 'updated_at';

    /**
     * Cache for a (manages attributes/columns)
     */
    protected ?object $A = null;

    /**
     * Cache for r (manages relations)
     */
    protected ?object $R = null;

    /**
     * Create a new Obvious model instance.
     */
    public function __construct(array $attributes = [])
    {
        if (
            $this->incrementing = $this->incrementing
                && (string)$this->primaryKey !== ''
                && ($this->keyType === 'int' || $this->keyType === 'integer')
        ) {
            $this->casts[$this->primaryKey] ??= 'int';
        }

        $this->bootIfNotBooted();

        $this->initializeTraits();

        $this->original = $this->attributes;

        if ([] === $attributes) {
            return;
        }

        $this->fill($attributes);
    }

    /**
     * Check if the model needs to be booted and if so, do it.
     */
    protected function bootIfNotBooted(): void
    {
        if (!isset(static::$booted[static::class])) {
            static::$booted[static::class] = true;

            $this->fireModelEvent('booting', false);

            static::booting();
            static::boot();
            static::booted();

            $this->fireModelEvent('booted', false);
        }
    }

    /**
     * Perform any actions required before the model boots.
     */
    protected static function booting(): void
    {
        //
    }

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        static::bootTraits();
    }

    /**
     * Boot all the bootable traits on the model.
     *
     * If you override static::$modelShouldAutoDiscoverAndBootTraits as false and your model uses other traits,
     *  you MUST boot and initialize those extra traits manually.
     *
     * Example for SoftDeletes Trait:
     *
     * 1) By overriding the boot method:
     *
     * protected static function boot()
     * {
     *   static::bootSoftDeletes();
     *
     *   // see also the explicit solution below
     *   static::$traitInitializers[static::class][] = 'initializeSoftDeletes';
     *   static::$traitInitializers[static::class] = \array_unique(static::$traitInitializers[static::class]);
     *
     *   parent::boot();
     * }
     *
     * 2) Explicitly initialize the trait by overriding the initializeTraits method:
     *
     * protected function initializeTraits()
     * {
     *     $this->initializeSoftDeletes(); // skip this particular call if you want deleted_at column TO NOT be casted
     *     parent::initializeTraits()
     * }
     *
     * Example for HasUuids:
     *
     * public $usesUniqueIds = true;
     */
    protected static function bootTraits(): void
    {
        if (!(static::$modelShouldAutoDiscoverAndBootTraits ?? true)) {
            return;
        }

        $class = static::class;

        $booted = [];

        static::$traitInitializers[$class] = [];

        foreach (class_uses_recursive($class) as $trait) {
            $method = 'boot' . class_basename($trait);

            if (\method_exists($class, $method) && !\in_array($method, $booted, true)) {
                forward_static_call([$class, $method]);

                $booted[] = $method;
            }

            if (method_exists($class, $method = 'initialize' . class_basename($trait))) {
                static::$traitInitializers[$class][] = $method;

                static::$traitInitializers[$class] = array_unique(
                    static::$traitInitializers[$class]
                );
            }
        }
    }

    /**
     * Initialize any initializable traits on the model.
     */
    protected function initializeTraits(): void
    {
        foreach ((static::$traitInitializers[static::class] ?? []) as $method) {
            $this->{$method}();
        }
    }

    /**
     * Perform any actions required after the model boots.
     */
    protected static function booted(): void
    {
        //
    }

    /**
     * Clear the list of booted models so they will be re-booted.
     */
    public static function clearBootedModels(): void
    {
        static::$booted = [];

        static::$globalScopes = [];
    }

    /**
     * Disables relationship model touching for the current class during given callback scope.
     */
    public static function withoutTouching(callable $callback): void
    {
        static::withoutTouchingOn([static::class], $callback);
    }

    /**
     * Disables relationship model touching for the given model classes during given callback scope.
     */
    public static function withoutTouchingOn(array $models, callable $callback): void
    {
        static::$ignoreOnTouch = array_values(array_merge(static::$ignoreOnTouch, $models));

        try {
            $callback();
        } finally {
            static::$ignoreOnTouch = array_values(array_diff(static::$ignoreOnTouch, $models));
        }
    }

    /**
     * Determine if the given model is ignoring touches.
     */
    public static function isIgnoringTouch(?string $class = null): bool
    {
        $class = $class ?: static::class;

        if (!get_class_vars($class)['timestamps'] || !$class::UPDATED_AT) {
            return true;
        }

        foreach (static::$ignoreOnTouch as $ignoredClass) {
            if ($class === $ignoredClass || is_subclass_of($class, $ignoredClass)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Indicate that models should prevent lazy loading, silently discarding attributes, and accessing missing
     * attributes.
     */
    public static function shouldBeStrict(bool $shouldBeStrict = true): void
    {
        static::preventLazyLoading($shouldBeStrict);
        static::preventSilentlyDiscardingAttributes($shouldBeStrict);
        static::preventAccessingMissingAttributes($shouldBeStrict);
    }

    /**
     * Prevent model relationships from being lazy loaded.
     */
    public static function preventLazyLoading(bool $value = true): void
    {
        static::$modelsShouldPreventLazyLoading = $value;
    }

    /**
     * Register a callback that is responsible for handling lazy loading violations.
     */
    public static function handleLazyLoadingViolationUsing(?\Closure $callback): void
    {
        static::$lazyLoadingViolationCallback = $callback;
    }

    /**
     * Prevent non-fillable attributes from being silently discarded.
     */
    public static function preventSilentlyDiscardingAttributes(bool $value = true): void
    {
        static::$modelsShouldPreventSilentlyDiscardingAttributes = $value;
    }

    /**
     * Register a callback that is responsible for handling discarded attribute violations.
     */
    public static function handleDiscardedAttributeViolationUsing(?\Closure $callback): void
    {
        static::$discardedAttributeViolationCallback = $callback;
    }

    /**
     * Prevent accessing missing attributes on retrieved models.
     */
    public static function preventAccessingMissingAttributes(bool $value = true): void
    {
        static::$modelsShouldPreventAccessingMissingAttributes = $value;
    }

    /**
     * Register a callback that is responsible for handling missing attribute violations.
     */
    public static function handleMissingAttributeViolationUsing(?\Closure $callback): void
    {
        static::$missingAttributeViolationCallback = $callback;
    }

    /**
     * Execute a callback without broadcasting any model events for all model types.
     */
    public static function withoutBroadcasting(callable $callback): mixed
    {
        $isBroadcasting = static::$isBroadcasting;

        static::$isBroadcasting = false;

        try {
            return $callback();
        } finally {
            static::$isBroadcasting = $isBroadcasting;
        }
    }

    /**
     * Fill the model with an array of attributes.
     *
     * @throws \MacropaySolutions\Kernel\Database\Obvious\MassAssignmentException
     */
    public function fill(array $attributes): static
    {
        if ([] === $attributes) {
            return $this;
        }

        $totallyGuarded = $this->totallyGuarded();

        $fillable = $this->fillableFromArray($attributes);

        foreach ($fillable as $key => $value) {
            // The developers may choose to place some attributes in the "fillable" array
            // which means only those attributes may be set through mass assignment to
            // the model, and all others will just get ignored for security reasons.
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);

                continue;
            }

            if ($totallyGuarded || static::preventsSilentlyDiscardingAttributes()) {
                if (isset(static::$discardedAttributeViolationCallback)) {
                    (static::$discardedAttributeViolationCallback)($this, [$key]);

                    continue;
                }

                throw new MassAssignmentException(
                    sprintf(
                        'Add [%s] to fillable property to allow mass assignment on [%s].',
                        $key,
                        static::class
                    )
                );
            }
        }

        if (
             static::preventsSilentlyDiscardingAttributes()
             && $attributes !== $fillable
        ) {
            $keys = array_diff(array_keys($attributes), array_keys($fillable));

            if (isset(static::$discardedAttributeViolationCallback)) {
                (static::$discardedAttributeViolationCallback)($this, $keys);

                return $this;
            }

            throw new MassAssignmentException(
                sprintf(
                    'Add fillable property [%s] to allow mass assignment on [%s].',
                    implode(', ', $keys),
                    static::class
                )
            );
        }

        return $this;
    }

    /**
     * Fill the model with an array of attributes. Force mass assignment.
     */
    public function forceFill(array $attributes): static
    {
        return static::unguarded(fn() => $this->fill($attributes));
    }

    /**
     * Qualify the given column name by the model's table.
     */
    public function qualifyColumn(string $column): string
    {
        if (\str_contains($column, '.')) {
            return $column;
        }

        return $this->getTable() . '.' . $column;
    }

    /**
     * Qualify the given columns with the model's table.
     *
     * @param array $columns
     * @return array
     */
    public function qualifyColumns(array $columns): array
    {
        return collect($columns)->map(function ($column) {
            return $this->qualifyColumn($column);
        })->all();
    }

    /**
     * Create a new instance of the given model.
     */
    public function newInstance(array $attributes = [], bool $exists = false): static
    {
        // This method just provides a convenient way for us to generate fresh model
        // instances of this current model. It is particularly useful during the
        // hydration of new objects via the Obvious query builder instances.
        $model = new static();

        $model->exists = $exists;

        $model->setConnection(
            $this->getConnectionName()
        );

        $model->setTable($this->getTable());

        $model->mergeCasts($this->casts);

        if ([] === $attributes) {
            return $model;
        }

        $model->fill((array)$attributes);

        return $model;
    }

    /**
     * Create a new model instance that is existing.
     */
    public function newFromBuilder(array $attributes = [], ?string $connection = null): static
    {
        $model = $this->newInstance([], true);

        $model->setRawAttributes((array)$attributes, true);

        $model->setConnection($connection ?: $this->getConnectionName());

        $model->fireModelEvent('retrieved', false);

        return $model;
    }

    /**
     * Begin querying the model on a given connection.
     */
    public static function on(?string $connection = null): Builder
    {
        // First we will just create a fresh instance of this model, and then we can set the
        // connection on the model so that it is used for the queries we execute, as well
        // as being set on every relation we retrieve without a custom connection name.
        $instance = new static();

        $instance->setConnection($connection);

        return $instance->newQuery();
    }

    /**
     * Begin querying the model on the write connection.
     */
    public static function onWriteConnection(): Builder
    {
        return static::query()->useWritePdo();
    }

    /**
     * Get all the models from the database.
     *
     * @return Collection<int, static>
     */
    public static function all(array|string $columns = ['*']): Collection
    {
        return static::query()->get(
            \is_array($columns) ? $columns : \func_get_args()
        );
    }

    /**
     * Begin querying a model with eager loading.
     */
    public static function with(array|string $relations): builder
    {
        return static::query()->with(
            \is_string($relations) ? \func_get_args() : $relations
        );
    }

    /**
     * Eager load relations on the model.
     */
    public function load(array|string $relations): static
    {
        $query = $this->newQueryWithoutRelationships()->with(
            \is_string($relations) ? \func_get_args() : $relations
        );

        $query->eagerLoadRelations([$this]);

        return $this;
    }

    /**
     * Eager load relations on the model if they are not already eager loaded.
     */
    public function loadMissing(array|string$relations): static
    {
        $relations = \is_string($relations) ? \func_get_args() : $relations;

        $this->newCollection([$this])->loadMissing($relations);

        return $this;
    }

    /**
     * Eager load relation's column aggregations on the model.
     */
    public function loadAggregate(array|string $relations, string $column, ?string $function = null): static
    {
        $this->newCollection([$this])->loadAggregate($relations, $column, $function);

        return $this;
    }

    /**
     * Eager load relation counts on the model.
     */
    public function loadCount(array|string $relations): static
    {
        $relations = is_string($relations) ? func_get_args() : $relations;

        return $this->loadAggregate($relations, '*', 'count');
    }

    /**
     * Eager load relation max column values on the model.
     */
    public function loadMax(array|string$relations, string $column): static
    {
        return $this->loadAggregate($relations, $column, 'max');
    }

    /**
     * Eager load relation min column values on the model.
     */
    public function loadMin(array|string $relations, string $column): static
    {
        return $this->loadAggregate($relations, $column, 'min');
    }

    /**
     * Eager load relation's column summations on the model.
     */
    public function loadSum(array|string $relations, string $column): static
    {
        return $this->loadAggregate($relations, $column, 'sum');
    }

    /**
     * Eager load relation average column values on the model.
     */
    public function loadAvg(array|string $relations, string $column): static
    {
        return $this->loadAggregate($relations, $column, 'avg');
    }

    /**
     * Eager load related model existence values on the model.
     */
    public function loadExists(array|string $relations): static
    {
        return $this->loadAggregate($relations, '*', 'exists');
    }

    /**
     * Increment a column's value by a given amount.
     */
    public function increment(string $column, float|int|string $amount = 1, array $extra = []): int
    {
        return $this->incrementOrDecrement($column, $amount, $extra, 'increment');
    }

    /**
     * Decrement a column's value by a given amount.
     */
    public function decrement(string $column, float|int|string $amount = 1, array $extra = []): int
    {
        return $this->incrementOrDecrement($column, $amount, $extra, 'decrement');
    }

    /**
     * Run the increment or decrement method on the model.
     */
    protected function incrementOrDecrement(string $column, float|int|string $amount, array $extra, string $method): int
    {
        if (!$this->exists) {
            return $this->newQueryWithoutRelationships()->{$method}($column, $amount, $extra);
        }

        $this->{$column} = \bcadd(
            $s1 = (string)$this->{$column},
            $s2 = (string)(
            $method === 'increment'
                ? $amount
                : \bcmul(
                    (string)$amount,
                    '-1',
                    $p = \max(0, \strlen((string)\strrchr((string)$amount, '.')) - 1)
                )
            ),
            \max(
                0,
                \strlen((string)\strrchr($s1, '.')) - 1,
                $p ?? \strlen((string)\strrchr($s2, '.')) - 1
            )
        );

        $this->forceFill($extra);

        if (!$this->isDirty() || $this->fireModelEvent('updating') === false) {
            return 0;
        }

        return (int)tap(
            $this->setKeysForSaveQuery($this->newQueryWithoutScopes())->{$method}($column, $amount, $extra),
            function () use ($column) {
                $this->syncChanges();

                $this->fireModelEvent('updated', false);

                $this->syncOriginalAttributes(\array_keys($this->changes));
            }
        );
    }

    /**
     * Update the model in the database.
     */
    public function update(array $attributes = [], array $options = []): bool
    {
        if (!$this->exists) {
            return false;
        }

        return $this->fill($attributes)->save($options);
    }

    /**
     * Update the model in the database within a transaction.
     *
     * @throws \Throwable
     */
    public function updateOrFail(array $attributes = [], array $options = []): bool
    {
        if (!$this->exists) {
            return false;
        }

        return $this->fill($attributes)->saveOrFail($options);
    }

    /**
     * Update the model in the database without raising any events.
     */
    public function updateQuietly(array $attributes = [], array $options = []): bool
    {
        if (!$this->exists) {
            return false;
        }

        return $this->fill($attributes)->saveQuietly($options);
    }

    /**
     * Increment a column's value by a given amount without raising any events.
     */
    public function incrementQuietly(string $column, float|int|string $amount = 1, array $extra = []): int
    {
        return static::withoutEvents(function () use ($column, $amount, $extra) {
            return $this->incrementOrDecrement($column, $amount, $extra, 'increment');
        });
    }

    /**
     * Decrement a column's value by a given amount without raising any events.
     */
    public function decrementQuietly(string $column, float|int|string $amount = 1, array $extra = []): int
    {
        return static::withoutEvents(function () use ($column, $amount, $extra) {
            return $this->incrementOrDecrement($column, $amount, $extra, 'decrement');
        });
    }

    /**
     * Save the model and all of its relationships.
     */
    public function push(): bool
    {
        if (!$this->save()) {
            return false;
        }

        // To sync all the relationships to the database, we will simply spin through
        // the relationships and save each model via this "push" method, which allows
        // us to recurse into all of these nested relations for the model instance.
        foreach ($this->relations as $models) {
            $models = $models instanceof Collection
                ? $models->all() : [$models];

            foreach (array_filter($models) as $model) {
                if (!$model->push()) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Save the model and all of its relationships without raising any events to the parent model.
     */
    public function pushQuietly(): bool
    {
        return static::withoutEvents(fn() => $this->push());
    }

    /**
     * Save the model to the database without raising any events.
     */
    public function saveQuietly(array $options = []): bool
    {
        return static::withoutEvents(fn() => $this->save($options));
    }

    /**
     * Save the model to the database.
     */
    public function save(array $options = []): bool
    {
        $query = $this->newModelQuery();

        // If the "saving" event returns false we'll bail out of the save and return
        // false, indicating that the save failed. This provides a chance for any
        // listeners to cancel save operations if validations fail or whatever.
        /** Saving event might change the model so, no point in calling $this->mergeAttributesFromCachedCasts() before */
        if ($this->fireModelEvent('saving') === false) {
            return false;
        }

        if ($this->exists) {
            /** $this->getDirty() will merge/sync attributes from cached casts objects */
            $isDirty = [] !== ($dirty = $this->getDirty());

            if (!$isDirty) {
                return true;
            }

            try {
                /** We will try to optimize the execution by caching $dirty array BUT,
                multiple set calls will be needed
                WHEN $this->usesTimestamps() or WHEN there are listeners for updating event because they can do changes
                so, $this->getDirtyForUpdate() and $this->syncChanges() will call $this->getDirty() which will call
                getAttributes() */
                if (!$this->getEventDispatcher()->hasListeners('obvious.updating: ' . $this::class)) {
                    $this->tmpDirty = $dirty;
                    unset($dirty);
                }

                if ($this->performUpdate($query)) {
                    $this->finishSave($options + ['touch' => $isDirty]);

                    return true;
                }

                return false;
            } finally {
                $this->tmpDirty = null;
                $this->tmpOriginalBeforeAfterEvents = null;
            }
        }

        /** $this->isDirty() will merge/sync attributes from cached casts objects */
        $isDirty = $this->isDirty();

        /** Multiple set calls are needed because:
        - $this->performInsert can do changes,
        - creating event can do changes so,
        $this->getAttributesForInsert() and $this->syncOriginal() will call $this->getAttributes() */

        try {
            $saved = $this->performInsert($query);

            if (
                '' === (string)$this->getConnectionName() &&
                ($connection = $query->getConnection()) instanceof Connection
            ) {
                $this->setConnection($connection->getName());
            }

            if ($saved) {
                $this->finishSave($options + ['touch' => $isDirty]);
            }
        } finally {
            $this->tmpOriginalBeforeAfterEvents = null;
        }

        return $saved;
    }

    /**
     * Save the model to the database within a transaction.
     *
     * @throws \Throwable
     */
    public function saveOrFail(array $options = []): bool
    {
        return $this->getConnection()->transaction(fn() => $this->save($options));
    }

    /**
     * Perform any actions that are necessary after the model is saved.
     */
    protected function finishSave(array $options): void
    {
        $this->fireModelEvent('saved', false);

        if ($options['touch'] ?? true) {
            $this->touchOwners();
        }

        if (isset($this->tmpOriginalBeforeAfterEvents)) {
            $this->original = $this->tmpOriginalBeforeAfterEvents;
            $this->tmpOriginalBeforeAfterEvents = null;

            return;
        }

        $this->syncOriginal();
    }

    /**
     * Perform a model update operation.
     */
    protected function performUpdate(Builder $query): bool
    {
        // If the updating event returns false, we will cancel the update operation so
        // developers can hook Validation systems into their models and cancel this
        // operation if the model does not pass validation. Otherwise, we update.
        if ($this->fireModelEvent('updating') === false) {
            return false;
        }

        // First we need to create a fresh query instance and touch the creation and
        // update timestamp on the model which are maintained by us for developer
        // convenience. Then we will just continue saving the model instances.
        if ($this->usesTimestamps()) {
            $this->tmpDirty = null;
            $this->updateTimestamps();
        }

        // Once we have run the update operation, we will fire the "updated" event for
        // this model instance. This will allow developers to hook into these after
        // models are updated, giving them a chance to do any special processing.
        if ([] === $dirty = $this->getDirtyForUpdate()) {
            return false;
        }

        $this->setKeysForSaveQuery($query)->update($dirty);

        $this->syncChanges();

        $this->tmpDirty = null;
        $this->tmpOriginalBeforeAfterEvents = $this->attributes;

        $this->fireModelEvent('updated', false);

        return true;
    }

    /**
     * Set the keys for a select query.
     */
    protected function setKeysForSelectQuery(Builder $query): Builder
    {
        return $query->where($this->getKeyName(), '=', $this->getKeyForSelectQuery());
    }

    /**
     * Get the primary key value for a select query.
     */
    protected function getKeyForSelectQuery(): mixed
    {
        return $this->original[$this->getKeyName()] ?? $this->getKey();
    }

    /**
     * Set the keys for a save update query.
     */
    protected function setKeysForSaveQuery(Builder $query): Builder
    {
        return $query->where($this->getKeyName(), '=', $this->getKeyForSaveQuery());
    }

    /**
     * Get the primary key value for a save query.
     */
    protected function getKeyForSaveQuery(): mixed
    {
        return $this->original[$this->getKeyName()] ?? $this->getKey();
    }

    /**
     * Perform a model insert operation.
     */
    protected function performInsert(Builder $query): bool
    {
        if ($this->usesUniqueIds) {
            $this->setUniqueIds();
        }

        if ($this->fireModelEvent('creating') === false) {
            return false;
        }

        // First we'll need to create a fresh query instance and touch the creation and
        // update timestamps on this model, which are maintained by us for developer
        // convenience. After, we will just continue saving these model instances.
        if ($this->usesTimestamps()) {
            $this->updateTimestamps();
        }

        // If the model has an incrementing key, we can use the "insertGetId" method on
        // the query builder, which will give us back the final inserted ID for this
        // table from the database. Not all tables have to be incrementing though.
        $attributes = $this->getAttributesForInsert();

        if ($this->incrementing) {
            $this->insertAndSetId($query, $attributes);
        } else {
            // If the table isn't incrementing we'll simply insert these attributes as they
            // are. These attribute arrays must contain an "id" column previously placed
            // there by the developer as the manually determined key for these models.
            if ([] === $attributes) {
                return false;
            }

            $query->insert($attributes);
        }

        $this->tmpOriginalBeforeAfterEvents = $this->attributes;

        // We will go ahead and set the exists property to true, so that it is set when
        // the created event is fired, just in case the developer tries to update it
        // during the event. This will allow them to do so and run an update here.
        $this->exists = true;

        $this->wasRecentlyCreated = true;

        $this->fireModelEvent('created', false);

        return true;
    }

    /**
     * Insert the given attributes and set the ID on the model.
     */
    protected function insertAndSetId(Builder $query, array $attributes): void
    {
        $id = $query->insertGetId($attributes, $keyName = $this->getKeyName());

        $this->setAttribute($keyName, $id);
    }

    /**
     * Destroy the models for the given IDs.
     */
    public static function destroy(BaseCollection|array|int|string $ids): int
    {
        if ($ids instanceof ObviousCollection) {
            $ids = $ids->modelKeys();
        }

        if ($ids instanceof BaseCollection) {
            $ids = $ids->all();
        }

        $ids = is_array($ids) ? $ids : func_get_args();

        if ([] === $ids) {
            return 0;
        }

        // We will actually pull the models from the database table and call delete on
        // each of them individually so that their events get fired properly with a
        // correct set of attributes in case the developers wants to check these.
        $key = ($instance = new static())->getKeyName();

        $count = 0;

        foreach ($instance->query()->whereIn($key, $ids)->get() as $model) {
            /** @var self $model */
            if ($model->delete()) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Delete the model from the database.
     *
     * @throws \LogicException
     */
    public function delete(): bool
    {
        // If the model doesn't exist, there is nothing to delete so we'll just return
        // immediately and not do anything else. Otherwise, we will continue with a
        // deletion process on the model, firing the proper events, and so forth.
        if (!$this->exists) {
            return false;
        }

        if ($this->fireModelEvent('deleting') === false) {
            return false;
        }

        // Here, we'll touch the owning models, verifying these timestamps get updated
        // for the models. This will allow any caching to get broken on the parents
        // by the timestamp. Then we will go ahead and delete the model instance.
        $this->touchOwners();

        $this->performDeleteOnModel();

        // Once the model has been deleted, we will fire off the deleted event so that
        // the developers may hook into post-delete operations. We will then return
        // a boolean true as the delete is presumably successful on the database.
        $this->fireModelEvent('deleted', false);

        return true;
    }

    /**
     * Delete the model from the database without raising any events.
     */
    public function deleteQuietly(): bool
    {
        return static::withoutEvents(fn() => $this->delete());
    }

    /**
     * Delete the model from the database within a transaction.
     *
     * @throws \Throwable
     */
    public function deleteOrFail(): ?bool
    {
        if (!$this->exists) {
            return false;
        }

        return $this->getConnection()->transaction(fn() => $this->delete());
    }

    /**
     * Force a hard delete on a soft deleted model.
     *
     * This method protects developers from running forceDelete when the trait is missing.
     */
    public function forceDelete(): bool
    {
        return $this->delete();
    }

    /**
     * Perform the actual delete query on this model instance.
     */
    protected function performDeleteOnModel(): void
    {
        $this->setKeysForSaveQuery($this->newModelQuery())->delete();

        $this->exists = false;
    }

    /**
     * Begin querying the model.
     */
    public static function query(): Builder
    {
        return (new static())->newQuery();
    }

    /**
     * Get a new query builder for the model's table.
     */
    public function newQuery(): Builder
    {
        return $this->registerGlobalScopes($this->newQueryWithoutScopes());
    }

    /**
     * Get a new query builder that doesn't have any global scopes or eager loading.
     */
    public function newModelQuery(): Builder
    {
        return $this->newObviousBuilder(
            $this->newBaseQueryBuilder()
        )->setModel($this);
    }

    /**
     * Get a new query builder with no relationships loaded.
     */
    public function newQueryWithoutRelationships(): Builder
    {
        return $this->registerGlobalScopes($this->newModelQuery());
    }

    /**
     * Register the global scopes for this builder instance.
     */
    public function registerGlobalScopes(Builder $builder): Builder
    {
        foreach ($this->getGlobalScopes() as $identifier => $scope) {
            $builder->withGlobalScope($identifier, $scope);
        }

        return $builder;
    }

    /**
     * Get a new query builder that doesn't have any global scopes.
     */
    public function newQueryWithoutScopes(): Builder
    {
        return $this->newModelQuery()
            ->with($this->with)
            ->withCount($this->withCount);
    }

    /**
     * Get a new query instance without a given scope.
     */
    public function newQueryWithoutScope(Scope|string $scope): Builder
    {
        return $this->newQuery()->withoutGlobalScope($scope);
    }

    /**
     * Get a new query to restore one or more models by their queueable IDs.
     */
    public function newQueryForRestoration(array|int $ids): Builder
    {
        return $this->newQueryWithoutScopes()->whereKey($ids);
    }

    /**
     * Create a new Obvious query builder for the model.
     */
    public function newObviousBuilder(QueryBuilder $query): Builder
    {
//        return new Builder($query);
        return \di(Builder::class, [$query]);
    }

    /**
     * Get a new query builder instance for the connection.
     */
    protected function newBaseQueryBuilder(): QueryBuilder
    {
        return $this->getConnection()->query();
    }

    /**
     * Create a new Obvious Collection instance.
     */
    public function newCollection(array $models = []): Collection
    {
//        return new Collection($models);
        return \di(Collection::class, [$models]);
    }

    /**
     * Create a new pivot model instance.
     */
    public function newPivot(self $parent, array $attributes, string $table, bool $exists, ?string $using = null): Pivot
    {
        return $using ? $using::fromRawAttributes($parent, $attributes, $table, $exists)
            : Pivot::fromAttributes($parent, $attributes, $table, $exists);
    }

    /**
     * Override this when needed
     */
    public static function segregatedScopesMap(): array
    {
        return [];
    }

    /**
     * Convert the model instance to an array.
     */
    public function toArray(): array
    {
        return \array_merge($this->attributesToArray(), $this->relationsToArray());
    }

    /**
     * Convert the model instance to JSON.
     *
     * @throws \MacropaySolutions\Kernel\Database\Obvious\JsonEncodingException
     */
    public function toJson(int $options = 0): string
    {
        try {
            $json = json_encode($this->jsonSerialize(), $options | JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw JsonEncodingException::forModel($this, $e->getMessage());
        }

        return $json;
    }

    /**
     * Convert the object into something JSON serializable.
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Reload a fresh model instance from the database.
     */
    public function fresh(array|string $with = []): ?static
    {
        if (!$this->exists) {
            return null;
        }

        return $this->setKeysForSelectQuery($this->newQueryWithoutScopes())
            ->useWritePdo()
            ->with(is_string($with) ? func_get_args() : $with)
            ->first();
    }

    /**
     * Reload the current model instance with fresh attributes from the database.
     */
    public function refresh(): static
    {
        if (!$this->exists) {
            return $this;
        }

        $this->setRawAttributes(
            $this->setKeysForSelectQuery($this->newQueryWithoutScopes())
                ->useWritePdo()
                ->firstOrFail()
                ->attributes
        );

        $this->load(
            collect($this->relations)->reject(function ($relation) {
                return $relation instanceof Pivot
                    || (is_object($relation) && in_array(AsPivot::class, class_uses_recursive($relation), true));
            })->keys()->all()
        );

        $this->syncOriginal();

        return $this;
    }

    /**
     * Clone the model into a new, non-existing instance.
     */
    public function replicate(?array $except = null): static
    {
        $defaults = array_values(array_filter([
            $this->getKeyName(),
            $this->getCreatedAtColumn(),
            $this->getUpdatedAtColumn(),
            ...$this->uniqueIds(),
        ]));

        $attributes = Arr::except(
            $this->getAttributes(),
            $except ? array_unique(array_merge($except, $defaults)) : $defaults
        );

        return tap(new static(), function ($instance) use ($attributes) {
            $instance->setRawAttributes($attributes);

            $instance->setRelations($this->relations);

            $instance->fireModelEvent('replicating', false);
        });
    }

    /**
     * Clone the model into a new, non-existing instance without raising any events.
     */
    public function replicateQuietly(?array $except = null): static
    {
        return static::withoutEvents(fn() => $this->replicate($except));
    }

    /**
     * Determine if two models have the same ID and belong to the same table.
     */
    public function is(?self $model): bool
    {
        return !is_null($model) &&
            $this->getKey() === $model->getKey() &&
            $this->getTable() === $model->getTable() &&
            $this->getConnectionName() === $model->getConnectionName();
    }

    /**
     * Determine if two models are not the same.
     */
    public function isNot(?self $model): bool
    {
        return !$this->is($model);
    }

    /**
     * Get the database connection for the model.
     */
    public function getConnection(): Connection
    {
        return static::resolveConnection($this->getConnectionName());
    }

    /**
     * Get the current connection name for the model.
     */
    public function getConnectionName(): ?string
    {
        return $this->connection;
    }

    /**
     * Set the connection associated with the model.
     */
    public function setConnection(?string $name): static
    {
        $this->connection = $name;

        return $this;
    }

    /**
     * Resolve a connection instance.
     */
    public static function resolveConnection(?string $connection = null): ConnectionInterface
    {
        return static::getConnectionResolver()->connection($connection);
    }

    /**
     * Get the connection resolver instance.
     */
    public static function getConnectionResolver(): Resolver
    {
        return (self::$resolver ??= Container::getInstance()->make('db'));
    }

    /**
     * Set the connection resolver instance.
     */
    public static function setConnectionResolver(Resolver $resolver): void
    {
        self::$resolver = $resolver;
    }

    /**
     * Unset the connection resolver for models.
     *
     * @return void
     */
    public static function unsetConnectionResolver(): void
    {
        self::$resolver = null;
    }

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return $this->table ?? Str::snake(Str::pluralStudly(class_basename($this)));
    }

    /**
     * Set the table associated with the model.
     */
    public function setTable(string $table): static
    {
        $this->table = $table;

        return $this;
    }

    /**
     * Get the primary key for the model.
     */
    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    /**
     * Set the primary key for the model.
     */
    public function setKeyName(string $key): static
    {
        $this->primaryKey = $key;

        return $this;
    }

    /**
     * Get the table qualified key name.
     */
    public function getQualifiedKeyName(): string
    {
        return $this->qualifyColumn($this->getKeyName());
    }

    /**
     * Get the auto-incrementing key type.
     */
    public function getKeyType(): string
    {
        return $this->keyType;
    }

    /**
     * Set the data type for the primary key.
     *
     * @param string $type
     * @return $this
     */
    public function setKeyType(string $type): static
    {
        $this->keyType = $type;

        return $this;
    }

    /**
     * Get the value of the model's primary key.
     */
    public function getKey(): mixed
    {
        return $this->getAttribute($this->getKeyName());
    }

    /**
     * Get the queueable identity for the entity.
     */
    public function getQueueableId(): mixed
    {
        return $this->getKey();
    }

    /**
     * Get the queueable relationships for the entity.
     */
    public function getQueueableRelations(): array
    {
        $relations = [];

        foreach ($this->getRelations() as $key => $relation) {
            if (!$this->isRelationInSegregatedRelationsMap($key)) {
                continue;
            }

            $relations[] = $key;

            if ($relation instanceof QueueableCollection) {
                foreach ($relation->getQueueableRelations() as $collectionValue) {
                    $relations[] = $key . '.' . $collectionValue;
                }
            }

            if ($relation instanceof QueueableEntity) {
                foreach ($relation->getQueueableRelations() as $entityValue) {
                    $relations[] = $key . '.' . $entityValue;
                }
            }
        }

        return array_unique($relations);
    }

    /**
     * Get the queueable connection for the entity.
     */
    public function getQueueableConnection(): ?string
    {
        return $this->getConnectionName();
    }

    /**
     * Get the default foreign key name for the model.
     */
    public function getForeignKey(): string
    {
        return Str::snake(class_basename($this)) . '_' . $this->getKeyName();
    }

    /**
     * Get the number of models to return per page.
     */
    public function getPerPage(): int
    {
        return $this->perPage;
    }

    /**
     * Set the number of models to return per page.
     */
    public function setPerPage(int $perPage): static
    {
        $this->perPage = $perPage;

        return $this;
    }

    /**
     * Determine if lazy loading is disabled.
     */
    public static function preventsLazyLoading(): bool
    {
        return static::$modelsShouldPreventLazyLoading;
    }

    /**
     * Determine if discarding guarded attribute fills is disabled.
     *
     * @return bool
     */
    public static function preventsSilentlyDiscardingAttributes(): bool
    {
        return static::$modelsShouldPreventSilentlyDiscardingAttributes;
    }

    /**
     * Determine if accessing missing attributes is disabled.
     */
    public static function preventsAccessingMissingAttributes(): bool
    {
        return static::$modelsShouldPreventAccessingMissingAttributes;
    }

    /**
     * Get the broadcast channel route definition that is associated with the given entity.
     */
    public function broadcastChannelRoute(): string
    {
        return str_replace('\\', '.', static::class) . '.{' . Str::camel(class_basename($this)) . '}';
    }

    /**
     * Get the broadcast channel name that is associated with the given entity.
     */
    public function broadcastChannel(): string
    {
        return \str_replace('\\', '.', static::class) . '.' . $this->getKey();
    }

    public function __clone()
    {
        $this->A = null;
        $this->R = null;
    }

    /**
     * Dynamically retrieve attributes on the model.
     */
    public function __get(string $key): mixed
    {
        if ($key === 'r') {
            return $this->R ??= new class (\WeakReference::create($this)) {
                public function __construct(protected \WeakReference $ownerRef)
                {
                }

                public function __get(string $key): mixed
                {
                    return $this->ownerRef->get()?->getRelationValue($key);
                }

                public function __set(string $key, mixed $value): void
                {
                    $this->ownerRef->get()?->setRelation($key, $value);
                }

                public function __isset(string $key): bool
                {
                    return (bool)$this->ownerRef->get()?->relationLoaded($key);
                }

                public function __unset(string $key): void
                {
                    $this->ownerRef->get()?->unsetRelation($key);
                }

                public function __call(string $method, array $parameters): Relation
                {
                    return $this->ownerRef->get()?->callSegregatedRelation($method, $parameters);
                }
            };
        }

        if ($key === 'a') {
            return $this->A ??= new class (\WeakReference::create($this)) {
                public function __construct(protected \WeakReference $ownerRef)
                {
                }

                public function __get(string $key): mixed
                {
                    return $this->ownerRef->get()?->getAttributeValue($key);
                }

                public function __set(string $key, mixed $value): void
                {
                    $this->ownerRef->get()?->setAttribute($key, $value);
                }

                public function __isset(string $key): bool
                {
                    return (bool)$this->ownerRef->get()?->attributeOffsetExists($key);
                }

                public function __unset(string $key): void
                {
                    $this->ownerRef->get()?->attributeOffsetUnset($key);
                }

                public function __call(string $method, array $parameters): mixed
                {
                    return $this->ownerRef->get()?->{$method}(...$parameters);
                }
            };
        }

        return $this->getAttribute($key);
    }

    /**
     * Dynamically set attributes on the model.
     */
    public function __set(string $key, mixed $value): void
    {
        $this->setAttribute($key, $value);
    }

    /**
     * Determine if the given attribute exists.
     */
    public function offsetExists(mixed $offset): bool
    {
        try {
            return null !== $this->getAttribute((string)$offset);
        } catch (MissingAttributeException) {
            return false;
        }
    }

    /**
     * Get the value for a given offset.
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->getAttribute((string)$offset);
    }

    /**
     * Set the value for a given offset.
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->setAttribute((string)$offset, $value);
    }

    /**
     * Unset the value for a given offset.
     */
    public function offsetUnset(mixed $offset): void
    {
        unset(
            $this->attributes[$offset],
            $this->relations[$offset],
        );
    }

    /**
     * Determine if an attribute or relation exists on the model.
     */
    public function __isset(mixed $key): bool
    {
        return $this->offsetExists($key);
    }

    /**
     * Unset an attribute on the model.
     */
    public function __unset(mixed $key): void
    {
        $this->offsetUnset($key);
    }

    /**
     * Handle dynamic method calls into the model.
     * @throws \BadMethodCallException
     */
    public function __call(string $method, array $parameters): Relation
    {
        if ($this->isRelationInSegregatedRelationsMap($method, false)) {
            return $this->callSegregatedRelation($method, $parameters);
        }

        throw new \BadMethodCallException(
            \sprintf('Call to undefined method %s::%s().', static::class, $method)
        );
    }

    /**
     * Convert the model to its string representation.
     */
    public function __toString(): string
    {
        return $this->escapeWhenCastingToString
            ? e($this->toJson())
            : $this->toJson();
    }

    /**
     * Indicate that the object's string representation should be escaped when __toString is invoked.
     */
    public function escapeWhenCastingToString(bool $escape = true): static
    {
        $this->escapeWhenCastingToString = $escape;

        return $this;
    }

    /**
     * Freeze the model into a pure primitive array (No Eloquent state bloat)
     */
    public function __serialize(): array
    {
        $serializeData = [
            'relations' => [],
        ];

        foreach ($this->getRelations() as $relationName => $relationData) {
            if ($relationData instanceof Collection) {
                $serializeData['relations'][$relationName] = [
                    'type' => 'collection',
                    'class' => $relationData->first() ? $relationData->first()::class : null,
                    'data' => $relationData->map(fn($model) => $model->__serialize())->all(),
                ];

                continue;
            }

            if ($relationData instanceof Model) {
                $serializeData['relations'][$relationName] = [
                    'type' => 'model',
                    'class' => $relationData::class,
                    'data' => $relationData->__serialize(),
                ];

                continue;
            }

            if ($relationData === null) {
                $serializeData['relations'][$relationName] = null;
            }
        }

        foreach(\array_diff_key(\get_object_vars($this), \array_flip(static::IGNORE_ON_SERIALIZE)) as $key => $val) {
            if (static::containsObject($val, 0)) {
                continue;
            }

            $serializeData[$key] = $val;
        }

        return $serializeData;
    }

    /**
     * Thaw the primitive array back into an active Eloquent Model
     */
    public function __unserialize(array $data): void
    {

        foreach($data as $key => $val) {
            if ($key !== 'relations') {
                $this->{$key} = $val;

                continue;
            }

            foreach ($val as $relationName => $relationMeta) {
                if ($relationMeta === null) {
                    $this->setRelation($relationName, null);

                    continue;
                }

                if ($relationMeta['type'] === 'collection') {
                    if ('' !== (string)$relationMeta['class']) {
                        $models = \array_map(
                            function($serializedData) use ($relationMeta) {
                                $model = new $relationMeta['class']();
                                $model->__unserialize($serializedData);

                                return $model;
                            },
                            $relationMeta['data']
                        );
                        $this->setRelation($relationName, \di(Collection::class, [$models]));

                        continue;
                    }

                    $this->setRelation($relationName, \di(Collection::class));

                    continue;
                }

                if ($relationMeta['type'] === 'model' && $relationMeta['class']) {
                    $model = new $relationMeta['class']();
                    $model->__unserialize($relationMeta['data']);

                    $this->setRelation($relationName, $model);
                }
            }
        }

        $this->bootIfNotBooted();

        $this->initializeTraits();
    }

    /**
     * Recursively check if a value is or contains an object.
     */
    protected static function containsObject(mixed $val, int $depth = 0): bool
    {
        if ($depth > 20 || \is_object($val)) {
            return true;
        }

        if (\is_array($val)) {
            foreach ($val as $item) {
                if (static::containsObject($item, $depth + 1)) {
                    return true;
                }
            }
        }

        return false;
    }
}
