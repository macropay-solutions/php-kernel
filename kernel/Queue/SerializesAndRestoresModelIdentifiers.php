<?php

namespace MacropaySolutions\Kernel\Queue;

use MacropaySolutions\Kernel\Contracts\Database\ModelIdentifier;
use MacropaySolutions\Kernel\Contracts\Queue\QueueableCollection;
use MacropaySolutions\Kernel\Contracts\Queue\QueueableEntity;
use MacropaySolutions\Kernel\Database\Obvious\Collection as ObviousCollection;
use MacropaySolutions\Kernel\Database\Obvious\Relations\Concerns\AsPivot;
use MacropaySolutions\Kernel\Database\Obvious\Relations\Pivot;

trait SerializesAndRestoresModelIdentifiers
{
    /**
     * Get the property value prepared for serialization.
     *
     * @param mixed $value
     * @param bool $withRelations
     * @return mixed
     */
    protected function getSerializedPropertyValue($value, $withRelations = true)
    {
        if ($value instanceof QueueableCollection) {
            return (new ModelIdentifier(
                $value->getQueueableClass(),
                $value->getQueueableIds(),
                $withRelations ? $value->getQueueableRelations() : [],
                $value->getQueueableConnection()
            ))->useCollectionClass(
                ($collectionClass = get_class($value)) !== ObviousCollection::class
                    ? $collectionClass
                    : null
            );
        }

        if ($value instanceof QueueableEntity) {
            return new ModelIdentifier(
                get_class($value),
                $value->getQueueableId(),
                $withRelations ? $value->getQueueableRelations() : [],
                $value->getQueueableConnection()
            );
        }

        return $value;
    }

    /**
     * Get the restored property value after deserialization.
     *
     * @param mixed $value
     * @return mixed
     */
    protected function getRestoredPropertyValue($value)
    {
        if (\is_array($value) && ($value['self'] ?? null) === ModelIdentifier::class) {
            if (
                !\isset($value['class'])
                || !\is_string($value['class'])
                || !\isset($value['id'])
                || !\is_array($relations = $value['relations'] ?? [])
            ) {
                return $value;
            }

            $value = (new ModelIdentifier(
                $value['class'],
                $value['id'],
                $relations,
                $value['connection'] ?? null
            ))->useCollectionClass($value['collectionClass'] ?? null);
        }

        if (!$value instanceof ModelIdentifier) {
            return $value;
        }

        return is_array($value->id)
            ? $this->restoreCollection($value)
            : $this->restoreModel($value);
    }

    /**
     * Restore a queueable collection instance.
     *
     * @param \MacropaySolutions\Kernel\Contracts\Database\ModelIdentifier $value
     * @return \MacropaySolutions\Kernel\Database\Obvious\Collection
     */
    protected function restoreCollection($value)
    {
        if (!$value->class || count($value->id) === 0) {
            return !is_null($value->collectionClass ?? null)
                ? new $value->collectionClass()
                : new ObviousCollection();
        }

        $collection = $this->getQueryForModelRestoration(
            (new $value->class())->setConnection($value->connection),
            $value->id
        )->useWritePdo()->get();

        if (
            is_a($value->class, Pivot::class, true) ||
            in_array(AsPivot::class, class_uses($value->class))
        ) {
            return $collection;
        }

        $collection = $collection->keyBy->getKey();

        $collectionClass = get_class($collection);

        return new $collectionClass(
            collect($value->id)->map(function ($id) use ($collection) {
                return $collection[$id] ?? null;
            })->filter()
        );
    }

    /**
     * Restore the model from the model identifier instance.
     *
     * @param \MacropaySolutions\Kernel\Contracts\Database\ModelIdentifier $value
     * @return \MacropaySolutions\Kernel\Database\Obvious\Model
     */
    public function restoreModel($value)
    {
        return $this->getQueryForModelRestoration(
            (new $value->class())->setConnection($value->connection),
            $value->id
        )->useWritePdo()->firstOrFail()->load($value->relations ?? []);
    }

    /**
     * Get the query for model restoration.
     *
     * @param \MacropaySolutions\Kernel\Database\Obvious\Model $model
     * @param array|int $ids
     * @return \MacropaySolutions\Kernel\Database\Obvious\Builder
     */
    protected function getQueryForModelRestoration($model, $ids)
    {
        return $model->newQueryForRestoration($ids);
    }
}
