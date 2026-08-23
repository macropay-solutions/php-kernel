<?php

namespace MacropaySolutions\Kernel\Database\Obvious\Concerns;

use BackedEnum;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use MacropaySolutions\Kernel\Contracts\Database\Obvious\Castable;
use MacropaySolutions\Kernel\Contracts\Database\Obvious\CastsInboundAttributes;
use MacropaySolutions\Kernel\Contracts\Support\Arrayable;
use MacropaySolutions\Kernel\Database\LazyLoadingViolationException;
use MacropaySolutions\Kernel\Database\Obvious\Casts\AsArrayObject;
use MacropaySolutions\Kernel\Database\Obvious\Casts\AsCollection;
use MacropaySolutions\Kernel\Database\Obvious\Casts\AsEncryptedArrayObject;
use MacropaySolutions\Kernel\Database\Obvious\Casts\AsEncryptedCollection;
use MacropaySolutions\Kernel\Database\Obvious\Casts\AsEnumArrayObject;
use MacropaySolutions\Kernel\Database\Obvious\Casts\AsEnumCollection;
use MacropaySolutions\Kernel\Database\Obvious\Casts\Json;
use MacropaySolutions\Kernel\Database\Obvious\InvalidCastException;
use MacropaySolutions\Kernel\Database\Obvious\JsonEncodingException;
use MacropaySolutions\Kernel\Database\Obvious\MissingAttributeException;
use MacropaySolutions\Kernel\Support\Arr;
use MacropaySolutions\Kernel\Support\Carbon;
use MacropaySolutions\Kernel\Support\Collection as BaseCollection;
use MacropaySolutions\Kernel\Support\Str;
use RuntimeException;

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
     * Temporary cache to avoid multiple getDirty calls generating multiple set calls for
     * sync/merge casted attributes to objects to persist the possible changes made to those objects
     */
    protected ?array $tmpDirtyIfAttributesAreSyncedFromCashedCasts = null;

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
     * The attributes that have been cast using custom classes.
     *
     * @var array
     */
    protected $classCastCache = [];

    /**
     * The built-in, primitive cast types supported by Obvious.
     *
     * @var string[]
     */
    protected static $primitiveCastTypes = [
        'array',
        'bool',
        'boolean',
        'collection',
        'custom_datetime',
        'date',
        'datetime',
        'decimal',
        'double',
        'encrypted',
        'encrypted:array',
        'encrypted:collection',
        'encrypted:json',
        'encrypted:object',
        'float',
        'hashed',
        'immutable_date',
        'immutable_datetime',
        'immutable_custom_datetime',
        'int',
        'integer',
        'json',
        'object',
        'real',
        'string',
        'timestamp',
    ];

    /**
     * The storage format of the model's date columns.
     *
     * @var string
     */
    protected $dateFormat;

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
     * The cache of the converted cast types.
     *
     * @var array
     */
    protected static $castTypeCache = [];

    /**
     * The encrypter instance that is used to encrypt attributes.
     *
     * @var \MacropaySolutions\Kernel\Contracts\Encryption\Encrypter|null
     */
    public static $encrypter;

    /**
     * Prevent updates
     * Note that relations can be loaded and updated during the lock
     */
    public function lockUpdates(bool $checkDirty = true): bool
    {
        if (
            !$this->exists
            || $this->tmpDirtyIfAttributesAreSyncedFromCashedCasts !== null
            || ($checkDirty && $this->isDirty())
        ) {
            return false;
        }

        $this->tmpDirtyIfAttributesAreSyncedFromCashedCasts = [];

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
     *  $this->classCastCache = [];
     * }
     *
     * Note that relations can be loaded during the lock
     */
    public function unlockUpdates(): bool
    {
        if ($this->hasUnlockedUpdates()) {
            return false;
        }

        $this->tmpDirtyIfAttributesAreSyncedFromCashedCasts = null;

        return true;
    }

    public function hasUnlockedUpdates(): bool
    {
        return $this->tmpDirtyIfAttributesAreSyncedFromCashedCasts !== [];
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
        $attributes = $this->addDateAttributesToArray(
            $attributes = $this->getArrayableAttributes()
        );

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
     * Add the date attributes to the attributes array.
     *
     * @param array $attributes
     * @return array
     */
    protected function addDateAttributesToArray(array $attributes)
    {
        foreach ($this->getDates() as $key) {
            if (!isset($attributes[$key])) {
                continue;
            }

            $attributes[$key] = $this->serializeDate(
                $this->asDateTime($attributes[$key])
            );
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

            if (
                $attributes[$key] instanceof DateTimeInterface &&
                $this->isClassCastable($key)
            ) {
                $attributes[$key] = $this->serializeDate($attributes[$key]);
            }

            if (isset($attributes[$key]) && $this->isClassSerializable($key)) {
                $attributes[$key] = $this->serializeClassCastableAttribute($key, $attributes[$key]);
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
            } elseif (is_null($value)) {
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
            if (isset($relation) || is_null($value)) {
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
            array_key_exists($key, $this->attributes) ||
            array_key_exists($key, $this->casts) ||
            $this->hasGetMutator($key) ||
            $this->isClassCastable($key)
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
        return $this->transformModelValue($key, $this->getAttributeFromArray($key, true));
    }

    /**
     * Get an attribute from the $attributes array without transformation
     * @see static::getAttributeValue
     *
     * @param string $key
     * @param bool $mergeAllAttributesFromCachedCasts
     * @return mixed
     */
    protected function getAttributeFromArray($key)
    {
        if (
            true === (\func_get_args()[1] ?? false)
            && ($this->hasGetMutator($key))
        ) {
            return $this->getAttributes()[$key] ?? null;
        }

        $this->mergeAttributesFromClassCasts($key);

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
     *
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    protected function mutateAttribute($key, $value)
    {
        return $this->resolveSegregatedAccessorClosure($key)->call($this, $value);
    }

    /**
     * Get the value of an attribute using its mutator for array conversion.
     *
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    protected function mutateAttributeForArray($key, $value)
    {
        if ($this->isClassCastable($key)) {
            $value = $this->getClassCastableAttributeValue($key, $value);
        } else {
            $value = $this->mutateAttribute($key, $value);
        }

        return $value instanceof Arrayable ? $value->toArray() : $value;
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

        if (is_null($value) && in_array($castType, static::$primitiveCastTypes)) {
            return $value;
        }

        // If the key is one of the encrypted castable types, we'll first decrypt
        // the value and update the cast type so we may leverage the following
        // logic for casting this value to any additionally specified types.
        if ($this->isEncryptedCastable($key)) {
            $value = $this->fromEncryptedString($value);

            $castType = Str::after($castType, 'encrypted:');
        }

        switch ($castType) {
            case 'int':
            case 'integer':
                return (int)$value;
            case 'real':
            case 'float':
            case 'double':
                return $this->fromFloat($value);
            case 'decimal':
                return $this->asDecimal($value, explode(':', $this->getCasts()[$key], 2)[1]);
            case 'string':
                return (string)$value;
            case 'bool':
            case 'boolean':
                return (bool)$value;
            case 'object':
                return $this->fromJson($value, true);
            case 'array':
            case 'json':
                return $this->fromJson($value);
            case 'collection':
                return new BaseCollection($this->fromJson($value));
            case 'date':
                return $this->asDate($value);
            case 'datetime':
            case 'custom_datetime':
                return $this->asDateTime($value);
            case 'immutable_date':
                return $this->asDate($value)->toImmutable();
            case 'immutable_custom_datetime':
            case 'immutable_datetime':
                return $this->asDateTime($value)->toImmutable();
            case 'timestamp':
                return $this->asTimestamp($value);
        }

        if ($this->isEnumCastable($key)) {
            return $this->getEnumCastableAttributeValue($key, $value);
        }

        if ($this->isClassCastable($key)) {
            return $this->getClassCastableAttributeValue($key, $value);
        }

        return $value;
    }

    /**
     * Cast the given attribute using a custom cast class.
     *
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    protected function getClassCastableAttributeValue($key, $value)
    {
        $caster = $this->resolveCasterClass($key);

        $objectCachingDisabled = $caster->withoutObjectCaching ?? false;

        if (isset($this->classCastCache[$key]) && !$objectCachingDisabled) {
            return $this->classCastCache[$key];
        } else {
            $value = $caster instanceof CastsInboundAttributes
                ? $value
                : $caster->get($this, $key, $value, $this->attributes);

            if (
                $caster instanceof CastsInboundAttributes ||
                !is_object($value) ||
                $objectCachingDisabled
            ) {
                unset($this->classCastCache[$key]);
            } else {
                $this->classCastCache[$key] = $value;
            }

            return $value;
        }
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
        if (is_null($value)) {
            return;
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

        if (isset(static::$castTypeCache[$castType])) {
            return static::$castTypeCache[$castType];
        }

        if ($this->isCustomDateTimeCast($castType)) {
            $convertedCastType = 'custom_datetime';
        } elseif ($this->isImmutableCustomDateTimeCast($castType)) {
            $convertedCastType = 'immutable_custom_datetime';
        } elseif ($this->isDecimalCast($castType)) {
            $convertedCastType = 'decimal';
        } elseif (class_exists($castType)) {
            $convertedCastType = $castType;
        } else {
            $convertedCastType = trim(strtolower($castType));
        }

        return static::$castTypeCache[$castType] = $convertedCastType;
    }

    /**
     * Increment or decrement the given attribute using the custom cast class.
     *
     * @param string $method
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    protected function deviateClassCastableAttribute($method, $key, $value)
    {
        return $this->resolveCasterClass($key)->{$method}(
            $this,
            $key,
            $value,
            $this->attributes
        );
    }

    /**
     * Serialize the given attribute using the custom cast class.
     *
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    protected function serializeClassCastableAttribute($key, $value)
    {
        return $this->resolveCasterClass($key)->serialize(
            $this,
            $key,
            $value,
            $this->attributes
        );
    }

    /**
     * Determine if the cast type is a custom date time cast.
     *
     * @param string $cast
     * @return bool
     */
    protected function isCustomDateTimeCast($cast)
    {
        return str_starts_with($cast, 'date:') ||
            str_starts_with($cast, 'datetime:');
    }

    /**
     * Determine if the cast type is an immutable custom date time cast.
     *
     * @param string $cast
     * @return bool
     */
    protected function isImmutableCustomDateTimeCast($cast)
    {
        return str_starts_with($cast, 'immutable_date:') ||
            str_starts_with($cast, 'immutable_datetime:');
    }

    /**
     * Determine if the cast type is a decimal cast.
     *
     * @param string $cast
     * @return bool
     */
    protected function isDecimalCast($cast)
    {
        return str_starts_with($cast, 'decimal:');
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
            $this->setMutatedAttributeValue($key, $value);

            return $this;
        }

        if (!is_null($value) && $this->isDateAttribute($key)) {
            // If an attribute is listed as a "date", we'll convert it from a DateTime
            // instance into a form proper for storage on the database tables using
            // the connection grammar's date format. We will auto set the values.
            $value = $this->fromDateTime($value);
        }

        if ($this->isEnumCastable($key)) {
            $this->setEnumCastableAttribute($key, $value);

            return $this;
        }

        if ($this->isClassCastable($key)) {
            $this->setClassCastableAttribute($key, $value);

            return $this;
        }

        if (!is_null($value) && $this->isJsonCastable($key)) {
            $value = $this->castAttributeAsJson($key, $value);
        }

        // If this attribute contains a JSON ->, we'll set the proper value in the
        // attribute's underlying array. This takes care of properly nesting an
        // attribute in the array's value in the case of deeply nested items.
        if (str_contains($key, '->')) {
            return $this->fillJsonAttribute($key, $value);
        }

        if (!is_null($value) && $this->isEncryptedCastable($key)) {
            $value = $this->castAttributeAsEncryptedString($key, $value);
        }

        if (!is_null($value) && $this->hasCast($key, 'hashed')) {
            $value = $this->castAttributeAsHashedString($key, $value);
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
     *
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    protected function setMutatedAttributeValue($key, $value)
    {
        return $this->resolveSegregatedMutatorClosure($key)->call($this, $value);
    }

    /**
     * Determine if the given attribute is a date or date castable.
     *
     * @param string $key
     * @return bool
     */
    protected function isDateAttribute($key)
    {
        return in_array($key, $this->getDates(), true) ||
            $this->isDateCastable($key);
    }

    /**
     * Set a given JSON attribute on the model.
     *
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    public function fillJsonAttribute($key, $value)
    {
        [$key, $path] = explode('->', $key, 2);

        $value = $this->asJson(
            $this->getArrayAttributeWithValue(
                $path,
                $key,
                $value
            )
        );

        $this->attributes[$key] = $this->isEncryptedCastable($key)
            ? $this->castAttributeAsEncryptedString($key, $value)
            : $value;

        if ($this->isClassCastable($key)) {
            unset($this->classCastCache[$key]);
        }

        return $this;
    }

    /**
     * Set the value of a class castable attribute.
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    protected function setClassCastableAttribute($key, $value)
    {
        $caster = $this->resolveCasterClass($key);

        $this->attributes = array_replace(
            $this->attributes,
            $this->normalizeCastClassResponse(
                $key,
                $caster->set(
                    $this,
                    $key,
                    $value,
                    $this->attributes
                )
            )
        );

        if (
            $caster instanceof CastsInboundAttributes ||
            !is_object($value) ||
            ($caster->withoutObjectCaching ?? false)
        ) {
            unset($this->classCastCache[$key]);
        } else {
            $this->classCastCache[$key] = $value;
        }
    }

    /**
     * Set the value of an enum castable attribute.
     *
     * @param string $key
     * @param \UnitEnum|string|int $value
     * @return void
     */
    protected function setEnumCastableAttribute($key, $value)
    {
        $enumClass = $this->getCasts()[$key];

        if (!isset($value)) {
            $this->attributes[$key] = null;
        } elseif (is_object($value)) {
            $this->attributes[$key] = $this->getStorableEnumValue($value);
        } else {
            $this->attributes[$key] = $this->getStorableEnumValue(
                $this->getEnumCaseFromValue($enumClass, $value)
            );
        }
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
     * Get an array attribute with the given key and value set.
     *
     * @param string $path
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    protected function getArrayAttributeWithValue($path, $key, $value)
    {
        return tap($this->getArrayAttributeByKey($key), function (&$array) use ($path, $value) {
            Arr::set($array, str_replace('->', '.', $path), $value);
        });
    }

    /**
     * Get an array attribute or return an empty array if it is not set.
     *
     * @param string $key
     * @return array
     */
    protected function getArrayAttributeByKey($key)
    {
        if (!isset($this->attributes[$key])) {
            return [];
        }

        return $this->fromJson(
            $this->isEncryptedCastable($key)
                ? $this->fromEncryptedString($this->attributes[$key])
                : $this->attributes[$key]
        );
    }

    /**
     * Cast the given attribute to JSON.
     *
     * @param string $key
     * @param mixed $value
     * @return string
     */
    protected function castAttributeAsJson($key, $value)
    {
        $value = $this->asJson($value);

        if ($value === false) {
            throw JsonEncodingException::forAttribute(
                $this,
                $key,
                json_last_error_msg()
            );
        }

        return $value;
    }

    /**
     * Encode the given value as JSON.
     *
     * @param mixed $value
     * @return string
     */
    protected function asJson($value)
    {
        return Json::encode($value);
    }

    /**
     * Decode the given JSON back into an array or object.
     *
     * @param string $value
     * @param bool $asObject
     * @return mixed
     */
    public function fromJson($value, $asObject = false)
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Json::decode($value, !$asObject);
    }

    /**
     * Decrypt the given encrypted string.
     *
     * @param string $value
     * @return mixed
     */
    public function fromEncryptedString($value)
    {
        return (static::$encrypter ?? \app('encrypter')->getFacadeRoot())->decrypt($value, false);
    }

    /**
     * Cast the given attribute to an encrypted string.
     *
     * @param string $key
     * @param mixed $value
     * @return string
     */
    protected function castAttributeAsEncryptedString($key, $value)
    {
        return (static::$encrypter ?? \app('encrypter')->getFacadeRoot())->encrypt($value, false);
    }

    /**
     * Set the encrypter instance that will be used to encrypt attributes.
     *
     * @param \MacropaySolutions\Kernel\Contracts\Encryption\Encrypter|null $encrypter
     * @return void
     */
    public static function encryptUsing($encrypter)
    {
        static::$encrypter = $encrypter;
    }

    /**
     * Cast the given attribute to a hashed string.
     *
     * @param string $key
     * @param mixed $value
     * @return string
     */
    protected function castAttributeAsHashedString($key, $value)
    {
        if ($value === null) {
            return null;
        }

        if (!\app('hash')->isHashed($value)) {
            return \app('hash')->make($value);
        }

        if (!\app('hash')->verifyConfiguration($value)) {
            throw new RuntimeException("Could not verify the hashed value's configuration.");
        }

        return $value;
    }

    /**
     * Decode the given float.
     *
     * @param mixed $value
     * @return mixed
     */
    public function fromFloat($value)
    {
        return match ((string)$value) {
            'Infinity' => INF,
            '-Infinity' => -INF,
            'NaN' => NAN,
            default => (float)$value,
        };
    }

    /**
     * Return a decimal as string.
     *
     * @param float|string $value
     * @param int $decimals
     * @return string
     */
    protected function asDecimal($value, $decimals)
    {
        $value = (string) $value;

        // Expand scientific notation to prevent float casting precision loss
        if (\is_numeric($value) && \stripos($value, 'e') !== false) {
            $parts = \explode('e', \strtolower($value));
            $base = $parts[0];
            $exponent = (int)$parts[1];

            // Memory safety guard: Hard limit at 1000 to prevent DoS via bcpow
            if ($exponent > 1000 || $exponent < -1000) {
                throw new \RuntimeException('Scientific notation exponent outside of allowed range.');
            }

            $baseDecimals = \str_contains($base, '.') ? \strlen(\explode('.', $base)[1]) : 0;

            $value = $exponent >= 0 ?
                \bcmul($base, \bcpow('10', (string)$exponent), \max(0, $baseDecimals - $exponent)) :
                \bcdiv($base, \bcpow('10', (string)\abs($exponent)), $baseDecimals + \abs($exponent));
        }

        if (PHP_VERSION_ID >= 80400) {
            return \bcround($value, $decimals);
        }

        // Polyfill ROUND_HALF_UP
        $addition = '0.' . \str_repeat('0', \max(0, (int)$decimals)) . '5';
        $addition = \str_starts_with($value, '-') ? "-{$addition}" : $addition;

        // Add 0.5 at the target decimal place and truncate via bcadd
        $rounded = \bcadd($value, $addition, (int)$decimals + 1);

        return \bcadd($rounded, '0', (int)$decimals);
    }

    /**
     * Return a timestamp as DateTime object with time set to 00:00:00.
     *
     * @param mixed $value
     * @return \MacropaySolutions\Kernel\Support\Carbon
     */
    protected function asDate($value)
    {
        return $this->asDateTime($value)->startOfDay();
    }

    /**
     * Return a timestamp as DateTime object.
     *
     * @param mixed $value
     * @return \MacropaySolutions\Kernel\Support\Carbon
     */
    protected function asDateTime($value)
    {
        // If this value is already a Carbon instance, we shall just return it as is.
        // This prevents us having to re-instantiate a Carbon instance when we know
        // it already is one, which wouldn't be fulfilled by the DateTime check.
        if ($value instanceof CarbonInterface) {
            return \appDate()->instance($value);
        }

        // If the value is already a DateTime instance, we will just skip the rest of
        // these checks since they will be a waste of time, and hinder performance
        // when checking the field. We will just return the DateTime right away.
        if ($value instanceof DateTimeInterface) {
            return \appDate()->parse(
                $value->format('Y-m-d H:i:s.u'),
                $value->getTimezone()
            );
        }

        // If this value is an integer, we will assume it is a UNIX timestamp's value
        // and format a Carbon object from this timestamp. This allows flexibility
        // when defining your date fields as they might be UNIX timestamps here.
        if (is_numeric($value)) {
            return \appDate()->createFromTimestamp($value);
        }

        // If the value is in simply year, month, day format, we will instantiate the
        // Carbon instances from that format. Again, this provides for simple date
        // fields on the database, while still supporting Carbonized conversion.
        if ($this->isStandardDateFormat($value)) {
            return \appDate()->instance(Carbon::createFromFormat('Y-m-d', $value)->startOfDay());
        }

        $format = $this->getDateFormat();

        // Finally, we will just assume this date is in the format used by default on
        // the database connection and use that format to create the Carbon object
        // that is returned back out to the developers after we convert it here.
        try {
            $date = \appDate()->createFromFormat($format, $value);
        } catch (InvalidArgumentException) {
            $date = false;
        }

        return $date ?: \appDate()->parse($value);
    }

    /**
     * Determine if the given value is a standard date format.
     *
     * @param string $value
     * @return bool
     */
    protected function isStandardDateFormat($value)
    {
        return preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value);
    }

    /**
     * Convert a DateTime to a storable string.
     *
     * @param mixed $value
     * @return string|null
     */
    public function fromDateTime($value)
    {
        return empty($value) ? $value : $this->asDateTime($value)->format(
            $this->getDateFormat()
        );
    }

    /**
     * Return a timestamp as unix timestamp.
     *
     * @param mixed $value
     * @return int
     */
    protected function asTimestamp($value)
    {
        return $this->asDateTime($value)->getTimestamp();
    }

    /**
     * Prepare a date for array / JSON serialization.
     *
     * @param \DateTimeInterface $date
     * @return string
     */
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date instanceof DateTimeImmutable ?
            CarbonImmutable::instance($date)->toJSON() :
            Carbon::instance($date)->toJSON();
    }

    /**
     * Get the attributes that should be converted to dates.
     *
     * @return array
     */
    public function getDates()
    {
        return $this->usesTimestamps() ? [
            $this->getCreatedAtColumn(),
            $this->getUpdatedAtColumn(),
        ] : [];
    }

    /**
     * Get the format for database stored dates.
     *
     * @return string
     */
    public function getDateFormat()
    {
        return $this->dateFormat ?: $this->getConnection()->getQueryGrammar()->getDateFormat();
    }

    /**
     * Set the date format used by the model.
     *
     * @param string $format
     * @return $this
     */
    public function setDateFormat($format)
    {
        $this->dateFormat = $format;

        return $this;
    }

    /**
     * Determine whether an attribute should be cast to a native type.
     *
     * @param string $key
     * @param array|string|null $types
     * @return bool
     */
    public function hasCast($key, $types = null)
    {
        if (array_key_exists($key, $this->getCasts())) {
            return $types ? in_array($this->getCastType($key), (array)$types, true) : true;
        }

        return false;
    }

    /**
     * Get the casts array.
     *
     * @return array
     */
    public function getCasts()
    {
        return $this->casts;
    }

    /**
     * Determine whether a value is Date / DateTime castable for inbound manipulation.
     *
     * @param string $key
     * @return bool
     */
    protected function isDateCastable($key)
    {
        return $this->hasCast($key, ['date', 'datetime', 'immutable_date', 'immutable_datetime']);
    }

    /**
     * Determine whether a value is Date / DateTime custom-castable for inbound manipulation.
     *
     * @param string $key
     * @return bool
     */
    protected function isDateCastableWithCustomFormat($key)
    {
        return $this->hasCast($key, ['custom_datetime', 'immutable_custom_datetime']);
    }

    /**
     * Determine whether a value is JSON castable for inbound manipulation.
     *
     * @param string $key
     * @return bool
     */
    protected function isJsonCastable($key)
    {
        return $this->hasCast(
            $key,
            [
                'array',
                'json',
                'object',
                'collection',
                'encrypted:array',
                'encrypted:collection',
                'encrypted:json',
                'encrypted:object',
            ]
        );
    }

    /**
     * Determine whether a value is an encrypted castable for inbound manipulation.
     *
     * @param string $key
     * @return bool
     */
    protected function isEncryptedCastable($key)
    {
        return $this->hasCast(
            $key,
            ['encrypted', 'encrypted:array', 'encrypted:collection', 'encrypted:json', 'encrypted:object']
        );
    }

    /**
     * Determine if the given key is cast using a custom class.
     *
     * @param string $key
     * @return bool
     *
     * @throws \MacropaySolutions\Kernel\Database\Obvious\InvalidCastException
     */
    protected function isClassCastable($key)
    {
        $casts = $this->getCasts();

        if (!array_key_exists($key, $casts)) {
            return false;
        }

        $castType = $this->parseCasterClass($casts[$key]);

        if (in_array($castType, static::$primitiveCastTypes)) {
            return false;
        }

        if (class_exists($castType)) {
            return true;
        }

        throw new InvalidCastException($this->getModel(), $key, $castType);
    }

    /**
     * Determine if the given key is cast using an enum.
     *
     * @param string $key
     * @return bool
     */
    protected function isEnumCastable($key)
    {
        $casts = $this->getCasts();

        if (!array_key_exists($key, $casts)) {
            return false;
        }

        $castType = $casts[$key];

        if (in_array($castType, static::$primitiveCastTypes)) {
            return false;
        }

        return enum_exists($castType);
    }

    /**
     * Determine if the key is deviable using a custom class.
     *
     * @param string $key
     * @return bool
     *
     * @throws \MacropaySolutions\Kernel\Database\Obvious\InvalidCastException
     */
    protected function isClassDeviable($key)
    {
        if (!$this->isClassCastable($key)) {
            return false;
        }

        $castType = $this->resolveCasterClass($key);

        return method_exists($castType::class, 'increment') && method_exists($castType::class, 'decrement');
    }

    /**
     * Determine if the key is serializable using a custom class.
     *
     * @param string $key
     * @return bool
     *
     * @throws \MacropaySolutions\Kernel\Database\Obvious\InvalidCastException
     */
    protected function isClassSerializable($key)
    {
        return !$this->isEnumCastable($key) &&
            $this->isClassCastable($key) &&
            method_exists($this->resolveCasterClass($key), 'serialize');
    }

    /**
     * Resolve the custom caster class for a given key.
     *
     * @param string $key
     * @return mixed
     */
    protected function resolveCasterClass($key)
    {
        $castType = $this->getCasts()[$key];

        $arguments = [];

        if (is_string($castType) && str_contains($castType, ':')) {
            $segments = explode(':', $castType, 2);

            $castType = $segments[0];
            $arguments = explode(',', $segments[1]);
        }

        if (is_subclass_of($castType, Castable::class)) {
            $castType = $castType::castUsing($arguments);
        }

        if (is_object($castType)) {
            return $castType;
        }

        return new $castType(...$arguments);
    }

    /**
     * Parse the given caster class, removing any arguments.
     *
     * @param string $class
     * @return string
     */
    protected function parseCasterClass($class)
    {
        return !str_contains($class, ':')
            ? $class
            : explode(':', $class, 2)[0];
    }

    /**
     * Merge the cast class and attribute cast attributes back into the model.
     *
     * @return void
     */
    protected function mergeAttributesFromCachedCasts()
    {
        $this->mergeAttributesFromClassCasts();
    }

    /**
     * Merge the cast class attributes back into the model.
     * @param string|array $keys
     * @return void
     */
    protected function mergeAttributesFromClassCasts()
    {
        $k = \func_get_args()[0] ?? null;

        $classCastCache = \is_string($k) || \is_array($k) ?
            \array_intersect_key($this->classCastCache, \array_flip(\array_values((array)$k))) :
            $this->classCastCache;

        foreach ($classCastCache as $key => $value) {
            $caster = $this->resolveCasterClass($key);

            $this->attributes = array_merge(
                $this->attributes,
                $caster instanceof CastsInboundAttributes
                    ? [$key => $value]
                    : $this->normalizeCastClassResponse($key, $caster->set($this, $key, $value, $this->attributes))
            );
        }
    }

    /**
     * Normalize the response from a custom class caster.
     *
     * @param string $key
     * @param mixed $value
     * @return array
     */
    protected function normalizeCastClassResponse($key, $value)
    {
        return is_array($value) ? $value : [$key => $value];
    }

    /**
     * Get all the current attributes on the model.
     * @param bool $withoutMergeAttributesFromCachedCasts
     * @return array
     */
    public function getAttributes()
    {
        if (true !== (\func_get_args()[0] ?? null)) {
            $this->mergeAttributesFromCachedCasts();
        }

        return $this->attributes;
    }

    /**
     * Get all the current attributes on the model for an insert operation.
     *
     * @return array
     */
    protected function getAttributesForInsert()
    {
        return $this->getAttributes();
    }

    /**
     * Set the array of model attributes. No checking is done.
     *
     * @param array $attributes
     * @param bool $sync
     * @return $this
     */
    public function setRawAttributes(array $attributes, $sync = false)
    {
        $this->attributes = $attributes;

        if ($sync) {
            $this->syncOriginal();
        }

        $this->classCastCache = [];

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

        if (isset($this->tmpDirtyIfAttributesAreSyncedFromCashedCasts)) {
            return [] !== $attributes ?
                \array_intersect_key($this->tmpDirtyIfAttributesAreSyncedFromCashedCasts, \array_flip($attributes)) :
                $this->tmpDirtyIfAttributesAreSyncedFromCashedCasts;
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
            /** This will call $this->mergeAttributesFromCachedCasts($key) before the if condition */
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

        if (is_null($attribute)) {
            return false;
        }

        if ($this->isDateAttribute($key) || $this->isDateCastableWithCustomFormat($key)) {
            return $this->fromDateTime($attribute) === $this->fromDateTime($original);
        }

        if ($this->hasCast($key, ['object', 'collection'])) {
            return $this->fromJson($attribute) === $this->fromJson($original);
        }

        if ($this->hasCast($key, ['real', 'float', 'double'])) {
            if ($original === null) {
                return false;
            }

            return abs(
                $this->castAttribute($key, $attribute) - $this->castAttribute($key, $original)
            ) < PHP_FLOAT_EPSILON * 4;
        }

        if ($this->hasCast($key, static::$primitiveCastTypes)) {
            return $this->castAttribute($key, $attribute) === $this->castAttribute($key, $original);
        }

        if (
            $this->isClassCastable($key) && Str::startsWith(
                $this->getCasts()[$key],
                [AsArrayObject::class, AsCollection::class]
            )
        ) {
            return $this->fromJson($attribute) === $this->fromJson($original);
        }

        if (
            $this->isClassCastable($key) && Str::startsWith(
                $this->getCasts()[$key],
                [AsEnumArrayObject::class, AsEnumCollection::class]
            )
        ) {
            return $this->fromJson($attribute) === $this->fromJson($original);
        }

        if (
            $this->isClassCastable($key) && $original !== null && Str::startsWith(
                $this->getCasts()[$key],
                [AsEncryptedArrayObject::class, AsEncryptedCollection::class]
            )
        ) {
            return $this->fromEncryptedString($attribute) === $this->fromEncryptedString($original);
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

        // If the attribute is listed as a date, we will convert it to a DateTime
        // instance on retrieval, which makes it quite convenient to work with
        // date fields without having to create a mutator for each property.
        if (
            $value !== null
            && \in_array($key, $this->getDates(), false)
        ) {
            return $this->asDateTime($value);
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
