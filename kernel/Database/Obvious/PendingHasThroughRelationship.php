<?php

namespace MacropaySolutions\Kernel\Database\Obvious;

use BadMethodCallException;
use MacropaySolutions\Kernel\Database\Obvious\Relations\HasMany;
use MacropaySolutions\Kernel\Database\Obvious\Relations\HasManyThrough;
use MacropaySolutions\Kernel\Support\Str;

class PendingHasThroughRelationship
{
    /**
     * The root model that the relationship exists on.
     *
     * @var Model
     */
    protected $rootModel;

    /**
     * The local relationship.
     *
     * @var HasMany|\MacropaySolutions\Kernel\Database\Obvious\Relations\HasOne
     */
    protected $localRelationship;

    /**
     * Create a pending has-many-through or has-one-through relationship.
     *
     * @param Model $rootModel
     * @param HasMany|\MacropaySolutions\Kernel\Database\Obvious\Relations\HasOne $localRelationship
     */
    public function __construct($rootModel, $localRelationship)
    {
        $this->rootModel = $rootModel;

        $this->localRelationship = $localRelationship;
    }

    /**
     * Define the distant relationship that this model has.
     *
     * @param string|(callable(Model): (\MacropaySolutions\Kernel\Database\Obvious\Relations\HasOne|HasMany)) $callback
     * @return HasManyThrough|\MacropaySolutions\Kernel\Database\Obvious\Relations\HasOneThrough
     */
    public function has($callback)
    {
        if (is_string($callback)) {
            $callback = fn() => $this->localRelationship->getRelated()->callSegregatedRelation($callback);
        }

        $distantRelation = $callback($this->localRelationship->getRelated());

        if ($distantRelation instanceof HasMany) {
            return $this->rootModel->hasManyThrough(
                $distantRelation->getRelated()::class,
                $this->localRelationship->getRelated()::class,
                $this->localRelationship->getForeignKeyName(),
                $distantRelation->getForeignKeyName(),
                $this->localRelationship->getLocalKeyName(),
                $distantRelation->getLocalKeyName(),
            );
        }

        return $this->rootModel->hasOneThrough(
            $distantRelation->getRelated()::class,
            $this->localRelationship->getRelated()::class,
            $this->localRelationship->getForeignKeyName(),
            $distantRelation->getForeignKeyName(),
            $this->localRelationship->getLocalKeyName(),
            $distantRelation->getLocalKeyName(),
        );
    }

    /**
     * Handle dynamic method calls into the model.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        if (Str::startsWith($method, 'has')) {
            return $this->has(Str::of($method)->after('has')->lcfirst()->toString());
        }

        throw new BadMethodCallException(
            sprintf(
                'Call to undefined method %s::%s()',
                static::class,
                $method
            )
        );
    }
}
