<?php

namespace MacropaySolutions\Kernel\Database\Obvious\Concerns;

trait HasTimestamps
{
    public const CREATED_AT_FORMAT = 'Y-m-d H:i:s';
    public const UPDATED_AT_FORMAT = 'Y-m-d H:i:s';
    public const DELETED_AT_FORMAT = 'Y-m-d H:i:s';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * The list of models classes that have timestamps temporarily disabled.
     *
     * @var array
     */
    protected static $ignoreTimestampsOn = [];

    /**
     * Update the model's update timestamp.
     */
    public function touch(?string $attribute = null, ?string $format = null): bool
    {
        if ($attribute) {
            $this->$attribute = \date($format ?? $this::UPDATED_AT_FORMAT);

            return $this->save();
        }

        if (!$this->usesTimestamps()) {
            return false;
        }

        $this->updateTimestamps();

        return $this->save();
    }

    /**
     * Update the model's update timestamp without raising any events.
     *
     * @param string|null $attribute
     * @return bool
     */
    public function touchQuietly($attribute = null)
    {
        return static::withoutEvents(fn() => $this->touch($attribute));
    }

    /**
     * Update the creation and update timestamps.
     *
     * @return $this
     */
    public function updateTimestamps()
    {
        $time = \date(static::UPDATED_AT_FORMAT);

        $updatedAtColumn = $this->getUpdatedAtColumn();

        if (null !== $updatedAtColumn) {
            if ($this->getAttributeValue($updatedAtColumn) === '') {
                $this->setUpdatedAt($this->getOriginal($updatedAtColumn));
            } elseif (!$this->isDirty($updatedAtColumn)) {
                $this->setUpdatedAt($time);
            }
        }

        $createdAtColumn = $this->getCreatedAtColumn();

        if (!$this->exists && !is_null($createdAtColumn) && !$this->isDirty($createdAtColumn)) {
            $this->setCreatedAt($time);
        }

        return $this;
    }

    /**
     * Set the value of the "created at" attribute.
     *
     * @param mixed $value
     * @return $this
     */
    public function setCreatedAt($value)
    {
        $this->{$this->getCreatedAtColumn()} = $value;

        return $this;
    }

    /**
     * Set the value of the "updated at" attribute.
     *
     * @param mixed $value
     * @return $this
     */
    public function setUpdatedAt($value)
    {
        $this->{$this->getUpdatedAtColumn()} = $value;

        return $this;
    }

    /**
     * Determine if the model uses timestamps.
     *
     * @return bool
     */
    public function usesTimestamps()
    {
        return $this->timestamps && !static::isIgnoringTimestamps($this::class);
    }

    /**
     * Get the name of the "created at" column.
     *
     * @return string|null
     */
    public function getCreatedAtColumn()
    {
        return static::CREATED_AT;
    }

    /**
     * Get the name of the "updated at" column.
     *
     * @return string|null
     */
    public function getUpdatedAtColumn()
    {
        return static::UPDATED_AT;
    }

    /**
     * Get the fully qualified "created at" column.
     *
     * @return string|null
     */
    public function getQualifiedCreatedAtColumn()
    {
        return $this->qualifyColumn($this->getCreatedAtColumn());
    }

    /**
     * Get the fully qualified "updated at" column.
     *
     * @return string|null
     */
    public function getQualifiedUpdatedAtColumn()
    {
        return $this->qualifyColumn($this->getUpdatedAtColumn());
    }

    /**
     * Disable timestamps for the current class during the given callback scope.
     *
     * @param callable $callback
     * @return mixed
     */
    public static function withoutTimestamps(callable $callback)
    {
        return static::withoutTimestampsOn([static::class], $callback);
    }

    /**
     * Disable timestamps for the given model classes during the given callback scope.
     *
     * @param array $models
     * @param callable $callback
     * @return mixed
     */
    public static function withoutTimestampsOn($models, $callback)
    {
        static::$ignoreTimestampsOn = array_values(array_merge(static::$ignoreTimestampsOn, $models));

        try {
            return $callback();
        } finally {
            static::$ignoreTimestampsOn = array_values(array_diff(static::$ignoreTimestampsOn, $models));
        }
    }

    /**
     * Determine if the given model is ignoring timestamps / touches.
     *
     * @param string|null $class
     * @return bool
     */
    public static function isIgnoringTimestamps($class = null)
    {
        $class ??= static::class;

        foreach (static::$ignoreTimestampsOn as $ignoredClass) {
            if ($class === $ignoredClass || is_subclass_of($class, $ignoredClass)) {
                return true;
            }
        }

        return false;
    }
}
