<?php

namespace MacropaySolutions\Kernel\Database\Obvious\Concerns;

trait HasTimestamps
{
    public const CREATED_AT_FORMAT = 'Y-m-d H:i:s';
    public const UPDATED_AT_FORMAT = 'Y-m-d H:i:s';

    /**
     * Indicates if the model should be timestamped.
     */
    public bool $timestamps = true;

    /**
     * The list of models classes that have timestamps temporarily disabled.
     */
    protected static array $ignoreTimestampsOn = [];

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
     */
    public function touchQuietly(?string $attribute = null): bool
    {
        return static::withoutEvents(fn() => $this->touch($attribute));
    }

    /**
     * Update the creation and update timestamps.s
     */
    public function updateTimestamps(): static
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
     */
    public function setCreatedAt(mixed $value): static
    {
        $this->setAttribute($this->getCreatedAtColumn(), $value);

        return $this;
    }

    /**
     * Set the value of the "updated at" attribute.
     */
    public function setUpdatedAt(mixed $value): static
    {
        $this->setAttribute($this->getUpdatedAtColumn(), $value);

        return $this;
    }

    /**
     * Determine if the model uses timestamps.
     */
    public function usesTimestamps(): bool
    {
        return $this->timestamps && !static::isIgnoringTimestamps($this::class);
    }

    /**
     * Get the name of the "created at" column.
     */
    public function getCreatedAtColumn(): ?string
    {
        return static::CREATED_AT;
    }

    /**
     * Get the name of the "updated at" column.
     */
    public function getUpdatedAtColumn(): ?string
    {
        return static::UPDATED_AT;
    }

    /**
     * Get the fully qualified "created at" column.
     */
    public function getQualifiedCreatedAtColumn(): string
    {
        return $this->qualifyColumn((string)$this->getCreatedAtColumn());
    }

    /**
     * Get the fully qualified "updated at" column.
     */
    public function getQualifiedUpdatedAtColumn(): string
    {
        return $this->qualifyColumn((string)$this->getUpdatedAtColumn());
    }

    /**
     * Disable timestamps for the current class during the given callback scope.
     */
    public static function withoutTimestamps(callable $callback): mixed
    {
        return static::withoutTimestampsOn([static::class], $callback);
    }

    /**
     * Disable timestamps for the given model classes during the given callback scope.
     */
    public static function withoutTimestampsOn(array $models, callable $callback): mixed
    {
        static::$ignoreTimestampsOn = \array_values(\array_merge(static::$ignoreTimestampsOn, $models));

        try {
            return $callback();
        } finally {
            static::$ignoreTimestampsOn = \array_values(\array_diff(static::$ignoreTimestampsOn, $models));
        }
    }

    /**
     * Determine if the given model is ignoring timestamps / touches.
     */
    public static function isIgnoringTimestamps(?string $class = null): bool
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
