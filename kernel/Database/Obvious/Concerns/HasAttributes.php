<?php

namespace MacropaySolutions\Kernel\Database\Obvious\Concerns;

use BackedEnum;
use MacropaySolutions\Kernel\Contracts\Support\Arrayable;
use MacropaySolutions\Kernel\Database\LazyLoadingViolationException;
use MacropaySolutions\Kernel\Database\Obvious\InvalidCastException;
use MacropaySolutions\Kernel\Database\Obvious\MissingAttributeException;
use MacropaySolutions\Kernel\Support\Arr;
use MacropaySolutions\Kernel\Support\Str;

trait HasAttributes
{
    /**
     * The model's attributes.
     *
     * @var array
     */
    protected $attributes = [];

    /**
     * The model attribute's original state.
     *
     * @var array
     */
    protected $original = [];

    /**
     * The changed model attributes.
     *
     * @var array
     */
    protected $changes = [];

    /**
     * Temporary cache to avoid multiple getDirty calls
     */
    protected ?array $tmpDirty = null;

    /**
     * The static cache of accessors blueprints across all model instances.
     * Structured as: [ModelFqn => [AttributeName => Closure]]
     */
    private static array $segregatedAccessorsGlobalMap = [];

    /**
     * The static cache of mutators blueprints across all model instances.
     * Structured as: [ModelFqn => [AttributeName => Closure]]
     */
    private static array $segregatedMutatorsGlobalMap = [];

    /**
     * The cache of normalized attribute keys.
     */
    private static array $normalizedMutatorsKeyCache = [];

    /**
     * Temporary original cache to prevent changes in created,updated,saved events from getting
     * into $original without being saved into DB
     */
    protected ?array $tmpOriginalBeforeAfterEvents = null;

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [];

    /**
     * The built-in, primitive cast types supported by Obvious.
     *
     * @var string[]
     */
    protected static $primitiveCastTypes = [
        'int',
        'string',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [];

    /**
     * Indicates whether attributes are snake cased on arrays.
     *
     * @var bool
     */
    public static $snakeAttributes = true;

    /**
     * The cache of the mutated attributes for each class.
     *
     * @var array
     */
    protected static $mutatorCache = [];

    /**
     * Prevent updates
     * Note that relations can be loaded and updated during the lock
     */
    public function lockUpdates(bool $checkDirty = true): bool
    {
        if (
            !$this->exists
            || $this->tmpDirty !== null
            || ($checkDirty && $this->isDirty())
        ) {
            return false;
        }

        $this->tmpDirty = [];

        return true;
    }

    /**
     * Unlock updates
     *
     * To reset the model's $attributes and get the changes from dirty applied during the lock use:
     *
     * if ($this->unlockUpdates()) {
     *  $dirty = $this->getDirty();
     *  $this->attributes = $this->original;
     * }
     *
     * Note that relations can be loaded during the lock
     */
    public function unlockUpdates(): bool
    {
        if ($this->hasUnlockedUpdates()) {
            return false;
        }

        $this->tmpDirty = null;

        return true;
    }

    public function hasUnlockedUpdates(): bool
    {
        return $this->tmpDirty !== [];
    }

    /**
     * Convert the model's attributes to an array.
     *
     * @return array
     */
    public function attributesToArray()
    {
        // If an attribute is a date, we will cast it to a string after converting it
        // to a DateTime / Carbon instance. This is so we will get some consistent
        // formatting while accessing attributes vs. arraying / JSONing a model.
        $attributes = $this->getArrayableAttributes();

        $attributes = $this->addMutatedAttributesToArray(
            $attributes,
            $mutatedAttributes = $this->getMutatedAttributes()
        );

        // Next we will handle any casts that have been setup for this model and cast
        // the values to their appropriate type. If the attribute has a mutator we
        // will not perform the cast on those attributes to avoid any confusion.
        $attributes = $this->addCastAttributesToArray(
            $attributes,
            $mutatedAttributes
        );

        // Here we will grab all the appended, calculated attributes to this model
        // as these attributes are not really in the attributes array, but are run
        // when we need to array or JSON the model for convenience to the coder.
        foreach ($this->getArrayableAppends() as $key) {
            $attributes[$key] = $this->mutateAttributeForArray($key, null);
        }

        return $attributes;
    }

    /**
     * Add the mutated attributes to the attributes array.
     *
     * @param array $attributes
     * @param array $mutatedAttributes
     * @return array
     */
    protected function addMutatedAttributesToArray(array $attributes, array $mutatedAttributes)
    {
        foreach ($mutatedAttributes as $key) {
            // We want to spin through all the mutated attributes for this model and call
            // the mutator for the attribute. We cache off every mutated attributes so
            // we don't have to constantly check on attributes that actually change.
            if (!array_key_exists($key, $attributes)) {
                continue;
            }

            // Next, we will call the mutator for this attribute so that we can get these
            // mutated attribute's actual values. After we finish mutating each of the
            // attributes we will return this final array of the mutated attributes.
            $attributes[$key] = $this->mutateAttributeForArray(
                $key,
                $attributes[$key]
            );
        }

        return $attributes;
    }

    /**
     * Add the casted attributes to the attributes array.
     *
     * @param array $attributes
     * @param array $mutatedAttributes
     * @return array
     */
    protected function addCastAttributesToArray(array $attributes, array $mutatedAttributes)
    {
        foreach ($this->getCasts() as $key => $value) {
            if (
                !array_key_exists($key, $attributes) ||
                in_array($key, $mutatedAttributes)
            ) {
                continue;
            }

            // Here we will cast the attribute. Then, if the cast is a date or datetime cast
            // then we will serialize the date for the array. This will convert the dates
            // to strings based on the date format specified for these Obvious models.
            $attributes[$key] = $this->castAttribute(
                $key,
                $attributes[$key]
            );

            // If the attribute cast was a date or a datetime, we will serialize the date as
            // a string. This allows the developers to customize how dates are serialized
            // into an array without affecting how they are persisted into the storage.
            if (
                isset($attributes[$key]) && in_array(
                    $value,
                    ['date', 'datetime', 'immutable_date', 'immutable_datetime']
                )
            ) {
                $attributes[$key] = $this->serializeDate($attributes[$key]);
            }

            if (
                isset($attributes[$key]) && ($this->isCustomDateTimeCast($value) ||
                    $this->isImmutableCustomDateTimeCast($value))
            ) {
                $attributes[$key] = $attributes[$key]->format(explode(':', $value, 2)[1]);
            }

            if ($this->isEnumCastable($key) && (!($attributes[$key] ?? null) instanceof Arrayable)) {
                $attributes[$key] = isset($attributes[$key]) ? $this->getStorableEnumValue($attributes[$key]) : null;
            }

            if ($attributes[$key] instanceof Arrayable) {
                $attributes[$key] = $attributes[$key]->toArray();
            }
        }

        return $attributes;
    }

    /**
     * Get an attribute array of all arrayable attributes.
     *
     * @return array
     */
    protected function getArrayableAttributes()
    {
        return $this->getArrayableItems($this->getAttributes());
    }

    /**
     * Get all the appendable values that are arrayable.
     *
     * @return array
     */
    protected function getArrayableAppends()
    {
        if (!count($this->appends)) {
            return [];
        }

        return $this->getArrayableItems(
            array_combine($this->appends, $this->appends)
        );
    }

    /**
     * Get the model's relationships in array form.
     *
     * @return array
     */
    public function relationsToArray()
    {
        $attributes = [];

        foreach ($this->getArrayableRelations() as $key => $value) {
            // If the values implement the Arrayable interface we can just call this
            // toArray method on the instances which will convert both models and
            // collections to their proper array form and we'll set the values.
            if ($value instanceof Arrayable) {
                $relation = $value->toArray();
            } elseif ($value === null) {
                // If the value is null, we'll still go ahead and set it in this list of
                // attributes, since null is used to represent empty relationships if
                // it has a has one or belongs to type relationships on the models.
                $relation = $value;
            }

            // If the relationships snake-casing is enabled, we will snake case this
            // key so that the relation attribute is snake cased in this returned
            // array to the developers, making this consistent with attributes.
            if (static::$snakeAttributes) {
                $key = Str::snake($key);
            }

            // If the relation value has been set, we will set it on this attributes
            // list for returning. If it was not arrayable or null, we'll not set
            // the value on the array because it is some type of invalid value.
            if (isset($relation) || $value === null) {
                $attributes[$key] = $relation;
            }

            unset($relation);
        }

        return $attributes;
    }

    /**
     * Get an attribute array of all arrayable relations.
     *
     * @return array
     */
    protected function getArrayableRelations()
    {
        return $this->getArrayableItems($this->relations);
    }

    /**
     * Get an attribute array of all arrayable values.
     *
     * @param array $values
     * @return array
     */
    protected function getArrayableItems(array $values)
    {
        if (count($this->getVisible()) > 0) {
            $values = array_intersect_key($values, array_flip($this->getVisible()));
        }

        if (count($this->getHidden()) > 0) {
            $values = array_diff_key($values, array_flip($this->getHidden()));
        }

        return $values;
    }

    /**
     * Get an attribute from the model.
     *
     * @param string $key
     * @return mixed
     */
    public function getAttribute($key)
    {
        if (!$key) {
            return;
        }

        // If the attribute exists in the attribute array or has a "get" mutator we will
        // get the attribute's value. Otherwise, we will proceed as if the developers
        // are asking for a relationship's value. This covers both types of values.
        if (
            \array_key_exists($key, $this->attributes) ||
            isset($this->casts[$key]) ||
            $this->hasGetMutator($key)
        ) {
            return $this->getAttributeValue($key);
        }

        return $this->isRelation($key)
            ? $this->getRelationValue($key)
            : $this->throwMissingAttributeExceptionIfApplicable($key);
    }

    /**
     * Either throw a missing attribute exception or return null depending on Obvious's configuration.
     *
     * @param string $key
     * @return null
     *
     * @throws \MacropaySolutions\Kernel\Database\Obvious\MissingAttributeException
     */
    protected function throwMissingAttributeExceptionIfApplicable($key)
    {
        if (
            $this->exists &&
            !$this->wasRecentlyCreated &&
            static::preventsAccessingMissingAttributes()
        ) {
            if (isset(static::$missingAttributeViolationCallback)) {
                return call_user_func(static::$missingAttributeViolationCallback, $this, $key);
            }

            throw new MissingAttributeException($this, $key);
        }

        return null;
    }

    /**
     * Get a plain attribute (not a relationship).
     *
     * @param string $key
     * @return mixed
     */
    public function getAttributeValue($key)
    {
        return $this->transformModelValue($key, $this->getAttributeFromArray($key));
    }

    /**
     * Get an attribute from the $attributes array without transformation
     * @see static::getAttributeValue
     *
     * @param string $key
     * @return mixed
     */
    protected function getAttributeFromArray($key)
    {
        return $this->attributes[$key] ?? null;
    }

    /**
     * Get a relationship.
     *
     * @param string $key
     * @return mixed
     */
    public function getRelationValue($key)
    {
        // If the key already exists in the relationships array, it just means the
        // relationship has already been loaded, so we'll just return it out of
        // here because there is no need to query within the relations twice.
        if ($this->relationLoaded($key)) {
            return $this->relations[$key];
        }

        if ($this->preventsLazyLoading) {
            $this->handleLazyLoadingViolation($key);
        }

        // If the "attribute" exists as a method on the model, we will just assume
        // it is a relationship and will load and return results from the query
        // and hydrate the relationship's value on the "relationships" array.
        return $this->getRelationshipFromMethod($key);
    }

    /**
     * Determine if the given key is a relationship method on the model.
     *
     * @param string $key
     * @return bool
     */
    public function isRelation($key)
    {
        return $this->isRelationInSegregatedRelationsMap($key);
    }

    /**
     * Handle a lazy loading violation.
     *
     * @param string $key
     * @return mixed
     */
    protected function handleLazyLoadingViolation($key)
    {
        if (isset(static::$lazyLoadingViolationCallback)) {
            return call_user_func(static::$lazyLoadingViolationCallback, $this, $key);
        }

        if (!$this->exists || $this->wasRecentlyCreated) {
            return;
        }

        throw new LazyLoadingViolationException($this, $key);
    }

    /**
     * Get a relationship value from the segregated Closures.
     *
     * @param string $method
     * @return mixed
     *
     * @throws \LogicException
     */
    protected function getRelationshipFromMethod($method)
    {
        return tap($this->callSegregatedRelation($method)->getResults(), function ($results) use ($method): void {
            $this->setRelation($method, $results);
        });
    }

    /**
     * Determine if a get mutator exists for an attribute.
     *
     * @param string $key
     * @return bool
     */
    public function hasGetMutator($key)
    {
        return $this->resolveSegregatedAccessorClosure($key) instanceof \Closure;
    }

    /**
     * Get the value of an attribute using its mutator.
     */
    protected function mutateAttribute(string $key, mixed $value): null|string|int|\BackedEnum
    {
        $value = $this->resolveSegregatedAccessorClosure($key)->call($this, $value);

        if (
            isset($value) &&
            !\is_int($value) &&
            !\is_string($value) && 
            !($value instanceof \BackedEnum)
        ) {
            throw new \RuntimeException(\sprintf(
                'Accessor for attribute "%s" on model "%s" must return int, string, \BackedEnum, or null.' .
                    'Returned "%s". ' .
                    'Mutable objects are strictly forbidden to prevent state-synchronization flaws.',
                $key,
                static::class,
                \get_debug_type($value)
            ));
        }

        return $value;
    }

    /**
     * Get the value of an attribute using its mutator for array conversion.
     */
    protected function mutateAttributeForArray(string $key, mixed $value): null|string|int
    {
        $value = $this->mutateAttribute($key, $value);

        return $value instanceof \BackedEnum ? $value->value : $value;
    }

    /**
     * Merge new casts with existing casts on the model.
     *
     * @param array $casts
     * @return $this
     */
    public function mergeCasts($casts)
    {
        $this->casts = array_merge($this->casts, $casts);

        return $this;
    }

    /**
     * Cast an attribute to a native PHP type.
     *
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    protected function castAttribute($key, $value)
    {
        $castType = $this->getCastType($key);

        if ($value === null && in_array($castType, static::$primitiveCastTypes)) {
            return $value;
        }

        // If the key is one of the encrypted castable types, we'll first decrypt
        // the value and update the cast type so we may leverage the following
        // logic for casting this value to any additionally specified types.
        $castType = $this->getCastType($key);

        return match ($castType) {
            'int' => (int)$value,
            'string' => (string)$value,
            default => $this->isEnumCastable($key)
                ? $this->getEnumCastableAttributeValue($key, $value)
                : throw new InvalidCastException($this, $key, $castType),
        };
    }

    /**
     * Cast the given attribute to an enum.
     *
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    protected function getEnumCastableAttributeValue($key, $value)
    {
        if ($value === null) {
            return null;
        }

        $castType = $this->getCasts()[$key];

        if ($value instanceof $castType) {
            return $value;
        }

        return $this->getEnumCaseFromValue($castType, $value);
    }

    /**
     * Get the type of cast for a model attribute.
     *
     * @param string $key
     * @return string
     */
    protected function getCastType($key)
    {
        $castType = $this->getCasts()[$key];

        if (\class_exists($castType)) {
            return $castType;
        }

        return \trim(\strtolower($castType));
    }

    /**
     * Set a given attribute on the model.
     *
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    public function setAttribute($key, $value)
    {
        // First we will check for the presence of a mutator for the set operation
        // which simply lets the developers tweak the attribute as it is set on
        // this model, such as "json_encoding" a listing of data for storage.
        if ($this->hasSetMutator($key)) {
            return $this->setMutatedAttributeValue($key, $value);
        }

        if ($this->isEnumCastable($key)) {
            $this->setEnumCastableAttribute($key, $value);

            return $this;
        }

        $this->attributes[$key] = $value;

        return $this;
    }

    /**
     * Determine if a set mutator exists for an attribute.
     *
     * @param string $key
     * @return bool
     */
    public function hasSetMutator($key)
    {
        return $this->resolveSegregatedMutatorClosure($key) instanceof \Closure;
    }

    /**
     * Set the value of an attribute using its mutator.
     */
    protected function setMutatedAttributeValue(string $key, mixed $value): static
    {
        $this->resolveSegregatedMutatorClosure($key)->call($this, $value);

        if (
            isset($this->attributes[$key]) &&
            !\is_int($this->attributes[$key]) &&
            !\is_string($this->attributes[$key]) && 
            !($this->attributes[$key] instanceof \BackedEnum)
        ) {
            throw new \RuntimeException(\sprintf(
                'The mutator for "%s" on model "%s" attempted ' .
                    'to store an object of type "%s" in the $attributes array. ' .
                    'Mutators must strictly store primitives (int, string, null) or ' .
                    '\BackedEnum to prevent memory leaks and PDO binding errors.',
                $key,
                static::class,
                \get_debug_type($this->attributes[$key])
            ));
        }

        return $this;
    }

    /**
     * Set the value of an enum castable attribute.
     */
    protected function setEnumCastableAttribute(string $key, \BackedEnum|string|int|null $value): void
    {
        $enumClass = $this->getCasts()[$key];

        if (!isset($value)) {
            $this->attributes[$key] = null;

            return;
        }

        if (\is_object($value)) {
            $this->attributes[$key] = $this->getStorableEnumValue($value);

            return;
        }

        $this->attributes[$key] = $this->getStorableEnumValue(
            $this->getEnumCaseFromValue($enumClass, $value)
        );
    }

    /**
     * Get an enum case instance from a given class and value.
     *
     * @param string $enumClass
     * @param string|int $value
     * @return \UnitEnum|\BackedEnum
     */
    protected function getEnumCaseFromValue($enumClass, $value)
    {
        return is_subclass_of($enumClass, BackedEnum::class)
            ? $enumClass::from($value)
            : constant($enumClass . '::' . $value);
    }

    /**
     * Get the storable value from the given enum.
     *
     * @param \UnitEnum|\BackedEnum $value
     * @return string|int
     */
    protected function getStorableEnumValue($value)
    {
        return $value instanceof BackedEnum
            ? $value->value
            : $value->name;
    }

    /**
     * Determine whether an attribute should be cast to a native type.
     */
    public function hasCast(string $key, array|string|null $types = null): bool
    {
        if (isset($this->getCasts()[$key])) {
            return $types ? in_array($this->getCastType($key), (array)$types, true) : true;
        }

        return false;
    }

    /**
     * Get the casts array.
     */
    public function getCasts(): array
    {
        return $this->casts;
    }

    /**
     * Determine if the given key is cast using an enum.
     */
    protected function isEnumCastable(string $key): bool
    {
        $casts = $this->getCasts();

        if (!isset($casts[$key]) || \in_array($casts[$key], static::$primitiveCastTypes, true)) {
            return false;
        }

        return \enum_exists($casts[$key]) && \is_subclass_of($casts[$key], \BackedEnum::class);
    }

    /**
     * Get all the current attributes on the model.
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Get all the current attributes on the model for an insert operation.
     */
    protected function getAttributesForInsert(): array
    {
        return $this->getAttributes();
    }

    /**
     * Set the array of model attributes. No checking is done.
     */
    public function setRawAttributes(array $attributes, bool $sync = false): static
    {
        $this->attributes = $attributes;

        if ($sync) {
            $this->syncOriginal();
        }

        return $this;
    }

    /**
     * Get the model's original attribute values.
     *
     * @param string|null $key
     * @param mixed $default
     * @return mixed|array
     */
    public function getOriginal($key = null, $default = null)
    {
        return (new static())->setRawAttributes(
            $this->original,
            $sync = true
        )->getOriginalWithoutRewindingModel($key, $default);
    }

    /**
     * Get the model's original attribute values.
     *
     * @param string|null $key
     * @param mixed $default
     * @return mixed|array
     */
    protected function getOriginalWithoutRewindingModel($key = null, $default = null)
    {
        if ($key) {
            return $this->transformModelValue(
                $key,
                Arr::get($this->original, $key, $default)
            );
        }

        return collect($this->original)->mapWithKeys(function ($value, $key) {
            return [$key => $this->transformModelValue($key, $value)];
        })->all();
    }

    /**
     * Get the model's raw original attribute values.
     *
     * @param string|null $key
     * @param mixed $default
     * @return mixed|array
     */
    public function getRawOriginal($key = null, $default = null)
    {
        return Arr::get($this->original, $key, $default);
    }

    /**
     * Get a subset of the model's attributes.
     *
     * @param array|mixed $attributes
     * @return array
     */
    public function only($attributes)
    {
        $results = [];

        foreach (is_array($attributes) ? $attributes : func_get_args() as $attribute) {
            $results[$attribute] = $this->getAttribute($attribute);
        }

        return $results;
    }

    /**
     * Sync the original attributes with the current.
     *
     * @return $this
     */
    public function syncOriginal()
    {
        $this->original = $this->getAttributes();

        return $this;
    }

    /**
     * Sync a single original attribute with its current value.
     *
     * @param string $attribute
     * @return $this
     */
    public function syncOriginalAttribute($attribute)
    {
        return $this->syncOriginalAttributes($attribute);
    }

    /**
     * Sync multiple original attribute with their current values.
     *
     * @param array|string $attributes
     * @return $this
     */
    public function syncOriginalAttributes($attributes)
    {
        $attributes = is_array($attributes) ? $attributes : func_get_args();

        foreach ($attributes as $attribute) {
            $this->original[$attribute] = $this->getAttributeFromArray($attribute);
        }

        return $this;
    }

    /**
     * Sync the changed attributes.
     *
     * @return $this
     */
    public function syncChanges()
    {
        $this->changes = $this->getDirty();

        return $this;
    }

    /**
     * Determine if the model or any of the given attribute(s) have been modified.
     *
     * @param array|string|null $attributes
     * @return bool
     */
    public function isDirty($attributes = null)
    {
        return [] !== $this->getDirty(\is_array($attributes) ? $attributes : \func_get_args());
    }

    /**
     * Determine if the model or all the given attribute(s) have remained the same.
     *
     * @param array|string|null $attributes
     * @return bool
     */
    public function isClean($attributes = null)
    {
        return !$this->isDirty(...func_get_args());
    }

    /**
     * Discard attribute changes and reset the attributes to their original state.
     *
     * @return $this
     */
    public function discardChanges()
    {
        [$this->attributes, $this->changes] = [$this->original, []];

        return $this;
    }

    /**
     * Determine if the model or any of the given attribute(s) were changed when the model was last saved.
     *
     * @param array|string|null $attributes
     * @return bool
     */
    public function wasChanged($attributes = null)
    {
        return $this->hasChanges(
            $this->getChanges(),
            is_array($attributes) ? $attributes : func_get_args()
        );
    }

    /**
     * Determine if any of the given attributes were changed when the model was last saved.
     *
     * @param array $changes
     * @param array|string|null $attributes
     * @return bool
     */
    protected function hasChanges($changes, $attributes = null)
    {
        // If no specific attributes were provided, we will just see if the dirty array
        // already contains any attributes. If it does we will just return that this
        // count is greater than zero. Else, we need to check specific attributes.
        if (empty($attributes)) {
            return count($changes) > 0;
        }

        // Here we will spin through every attribute and see if this is in the array of
        // dirty attributes. If it is, we will return true and if we make it through
        // all the attributes for the entire array we will return false at end.
        foreach (Arr::wrap($attributes) as $attribute) {
            if (array_key_exists($attribute, $changes)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the attributes that have been changed since the last sync.
     * @param string|array $attributes
     * @return array
     */
    public function getDirty()
    {
        $args = \func_get_args();
        $attributes = (array)($args[0] ?? []);

        if (isset($this->tmpDirty)) {
            return [] !== $attributes ?
                \array_intersect_key($this->tmpDirty, \array_flip($attributes)) :
                $this->tmpDirty;
        }

        $dirty = [];

        if ([] === $attributes) {
            foreach ($this->getAttributes() as $key => $value) {
                if (!$this->originalIsEquivalent($key)) {
                    $dirty[$key] = $value;
                }
            }

            return $dirty;
        }

        foreach ($attributes as $key) {
            $value = $this->getAttributeFromArray($key);

            if (!$this->originalIsEquivalent($key)) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    /**
     * Get the attributes that have been changed since the last sync for an update operation.
     *
     * @return array
     */
    protected function getDirtyForUpdate()
    {
        return $this->getDirty();
    }

    /**
     * Get the attributes that were changed when the model was last saved.
     *
     * @return array
     */
    public function getChanges()
    {
        return $this->changes;
    }

    /**
     * Determine if the new and old values for a given key are equivalent.
     *
     * @param string $key
     * @return bool
     */
    public function originalIsEquivalent($key)
    {
        if (!array_key_exists($key, $this->original)) {
            return false;
        }

        $attribute = Arr::get($this->attributes, $key);
        $original = Arr::get($this->original, $key);

        if ($attribute === $original) {
            return true;
        }

        if ($attribute === null) {
            return false;
        }

        if ($this->hasCast($key, static::$primitiveCastTypes)) {
            return $this->castAttribute($key, $attribute) === $this->castAttribute($key, $original);
        }

        return is_numeric($attribute) && is_numeric($original) && strcmp((string)$attribute, (string)$original) === 0;
    }

    /**
     * Transform a raw model value using mutators, casts, etc.
     *
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    protected function transformModelValue($key, $value)
    {
        // If the attribute has a get mutator, we will call that then return what
        // it returns as the value, which is useful for transforming values on
        // retrieval from the model to a form that is more useful for usage.
        if ($this->hasGetMutator($key)) {
            return $this->mutateAttribute($key, $value);
        }

        // If the attribute exists within the cast array, we will convert it to
        // an appropriate native PHP type dependent upon the associated value
        // given with the key in the pair. Dayle made this comment line up.
        if ($this->hasCast($key)) {
            if (
                static::preventsAccessingMissingAttributes() &&
                !array_key_exists($key, $this->attributes) &&
                ($this->isEnumCastable($key) ||
                    in_array($this->getCastType($key), static::$primitiveCastTypes))
            ) {
                $this->throwMissingAttributeExceptionIfApplicable($key);
            }

            return $this->castAttribute($key, $value);
        }

        return $value;
    }

    /**
     * Append attributes to query when building a query.
     *
     * @param array|string $attributes
     * @return $this
     */
    public function append($attributes)
    {
        $this->appends = array_values(
            array_unique(
                array_merge($this->appends, is_string($attributes) ? func_get_args() : $attributes)
            )
        );

        return $this;
    }

    /**
     * Get the accessors that are being appended to model arrays.
     *
     * @return array
     */
    public function getAppends()
    {
        return $this->appends;
    }

    /**
     * Set the accessors to append to model arrays.
     *
     * @param array $appends
     * @return $this
     */
    public function setAppends(array $appends)
    {
        $this->appends = $appends;

        return $this;
    }

    /**
     * Return whether the accessor attribute has been appended.
     *
     * @param string $attribute
     * @return bool
     */
    public function hasAppended($attribute)
    {
        return in_array($attribute, $this->appends);
    }

    /**
     * Get the mutated attributes for a given instance.
     *
     * @return array
     */
    public function getMutatedAttributes()
    {
        if (!isset(static::$mutatorCache[static::class])) {
            static::cacheMutatedAttributes($this);
        }

        return static::$mutatorCache[static::class];
    }

    /**
     * Extract and cache all the mutated attributes of a class.
     *
     * @param object|string $classOrInstance
     * @return void
     */
    public static function cacheMutatedAttributes($classOrInstance)
    {
        $instance = \is_object($classOrInstance) ? $classOrInstance : new $classOrInstance();

        static::$mutatorCache[$instance::class] = \array_keys(
            \array_filter($instance->thisSegregatedAccessorsDefinitionMap())
        );
    }

    /**
     * Override this if you prefer to separate your accessors definitions from the model's methods.
     * DO NOT USE static closures!
     * There is no check/validation for this structure so make sure you declare it right.
     *
     * return [
     *  'first_name' => fn($value) => ucfirst($value),
     *  // Accessing other attributes via $this:
     *  'full_name' => fn() => $this->first_name . ' ' . $this->last_name,
     *  // Reuse the method accessor:
     *  'first_name' => fn($value) => $this->nameAsMethod($value),
     *  // AVOID THESE:
     *  'first_name' => $this->nameAsMethod(...), // hard-binds closure scope to the first booted model
     *  'first_name' => [$this, 'nameAsMethod'], // is not a Closure
     *  'first_name' => fn($value) => [$this, 'nameAsMethod']($value),
     * ];
     *
     */
    protected function segregatedAccessorsMap(): array
    {
        return [];
    }

    /**
     * Override this if you prefer to separate your mutators definitions from the model's methods.
     * DO NOT USE static closures!
     * There is no check/validation for this structure so make sure you declare it right.
     *
     * return [
     *  'first_name' => function($value) { $this->attributes['first_name'] = strtolower($value); },
     *  // Reuse the method mutator:
     *  'first_name' => function($value) { $this->nameAsMethod($value); },
     *  // AVOID THESE:
     *  'first_name' => $this->nameAsMethod(...), // hard-binds closure scope to the first booted model
     *  'first_name' => [$this, 'nameAsMethod'], // is not a Closure
     *  'first_name' => fn($value) => [$this, 'nameAsMethod']($value),
     * ];
     *
     */
    protected function segregatedMutatorsMap(): array
    {
        return [];
    }

    private function thisSegregatedAccessorsDefinitionMap(): array
    {
        if (isset(self::$segregatedAccessorsGlobalMap[static::class])) {
            return self::$segregatedAccessorsGlobalMap[static::class];
        }

        $map = [];

        foreach ($this->segregatedAccessorsMap() as $key => $closure) {
            $map[self::getNormalizedMutatorKey($key)] = static::unbindClosure($closure);
        }

        return self::$segregatedAccessorsGlobalMap[static::class] = $map;
    }

    private function thisSegregatedMutatorsDefinitionMap(): array
    {
        if (isset(self::$segregatedMutatorsGlobalMap[static::class])) {
            return self::$segregatedMutatorsGlobalMap[static::class];
        }

        $map = [];

        foreach ($this->segregatedMutatorsMap() as $key => $closure) {
            $map[self::getNormalizedMutatorKey($key)] = static::unbindClosure($closure);
        }

        return self::$segregatedMutatorsGlobalMap[static::class] = $map;
    }

    private function resolveSegregatedAccessorClosure(string $key): ?\Closure
    {
        $cached =
            self::$segregatedAccessorsGlobalMap[static::class][$key = self::getNormalizedMutatorKey($key)] ?? null;

        if ($cached !== null) {
            return $cached ?: null;
        }

        return (self::$segregatedAccessorsGlobalMap[static::class][$key] ??=
            $this->thisSegregatedAccessorsDefinitionMap()[$key] ?? false) ?: null;
    }

    private function resolveSegregatedMutatorClosure(string $key): ?\Closure
    {
        $cached =
            self::$segregatedMutatorsGlobalMap[static::class][$key = self::getNormalizedMutatorKey($key)] ?? null;

        if ($cached !== null) {
            return $cached ?: null;
        }

        return (self::$segregatedMutatorsGlobalMap[static::class][$key] ??=
            $this->thisSegregatedMutatorsDefinitionMap()[$key] ?? false) ?: null;
    }

    private static function getNormalizedMutatorKey(string $key): string
    {
        return self::$normalizedMutatorsKeyCache[$key] ??= \lcfirst(
            static::$snakeAttributes ? Str::snake($key) : $key
        );
    }
}
