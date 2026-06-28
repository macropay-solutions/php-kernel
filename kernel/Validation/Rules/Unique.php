<?php

namespace MacropaySolutions\Kernel\Validation\Rules;

use MacropaySolutions\Kernel\Database\Obvious\Model;
use MacropaySolutions\Kernel\Support\Traits\Conditionable;

class Unique
{
    use Conditionable;
    use DatabaseRule;

    /**
     * The ID that should be ignored.
     *
     * @var mixed
     */
    protected $ignore;

    /**
     * The name of the ID column.
     *
     * @var string
     */
    protected $idColumn = 'id';

    /**
     * Ignore the given ID during the unique check.
     *
     * @param mixed $id
     * @param string|null $idColumn
     * @return $this
     */
    public function ignore($id, $idColumn = null)
    {
        if ($id instanceof Model) {
            return $this->ignoreModel($id, $idColumn);
        }

        $this->ignore = $id;
        $this->idColumn = $idColumn ?? 'id';

        return $this;
    }

    /**
     * Ignore the given model during the unique check.
     *
     * @param \MacropaySolutions\Kernel\Database\Obvious\Model $model
     * @param string|null $idColumn
     * @return $this
     */
    public function ignoreModel($model, $idColumn = null)
    {
        $this->idColumn = $idColumn ?? $model->getKeyName();
        $this->ignore = $model->{$this->idColumn};

        return $this;
    }

    /**
     * Convert the rule to a validation string.
     *
     * @return string
     */
    public function __toString()
    {
        return rtrim(
            sprintf(
                'unique:%s,%s,%s,%s,%s',
                $this->table,
                $this->column,
                $this->ignore ? '"' . addslashes($this->ignore) . '"' : 'NULL',
                $this->idColumn,
                $this->formatWheres()
            ),
            ','
        );
    }

    /**
     * Get the ID that should be ignored.
     */
    public function getIgnoreId(): mixed
    {
        return $this->ignore;
    }

    /**
     * Get the name of the ID column.
     */
    public function getIdColumn(): string
    {
        return $this->idColumn;
    }
}
