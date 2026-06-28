<?php

namespace MacropaySolutions\Kernel\Database\Obvious;

use MacropaySolutions\Kernel\Database\RecordsNotFoundException;
use MacropaySolutions\Kernel\Support\Arr;

/**
 * @template TModel of \MacropaySolutions\Kernel\Database\Obvious\Model
 */
class ModelNotFoundException extends RecordsNotFoundException
{
    /**
     * Name of the affected Obvious model.
     *
     * @var class-string<TModel>
     */
    protected $model;

    /**
     * The affected model IDs.
     *
     * @var array<int, int|string>
     */
    protected $ids;

    /**
     * Set the affected Obvious model and instance ids.
     *
     * @param class-string<TModel> $model
     * @param array<int, int|string>|int|string $ids
     * @return $this
     */
    public function setModel($model, $ids = [])
    {
        $this->model = $model;
        $this->ids = Arr::wrap($ids);

        $this->message = "No query results for model [{$model}]";

        if (count($this->ids) > 0) {
            $this->message .= ' ' . implode(', ', $this->ids);
        } else {
            $this->message .= '.';
        }

        return $this;
    }

    /**
     * Get the affected Obvious model.
     *
     * @return class-string<TModel>
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * Get the affected Obvious model IDs.
     *
     * @return array<int, int|string>
     */
    public function getIds()
    {
        return $this->ids;
    }
}
