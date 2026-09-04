<?php

namespace MacropaySolutions\Kernel\Database\Obvious\Concerns;

trait HasUniqueIds
{
    /**
     * Indicates if the model uses unique ids.
     */
    public bool $usesUniqueIds = false;

    /**
     * Generate unique keys for the model.
     */
    public function setUniqueIds(): void
    {
        foreach ($this->uniqueIds() as $column) {
            if ('' === (string)$this->getAttributeValue($column)) {
                $this->setAttribute($column, $this->newUniqueId());
            }
        }
    }

    /**
     * Generate a new key for the model.
     */
    public function newUniqueId(): string
    {
        return '';
    }

    /**
     * Get the columns that should receive a unique identifier.
     */
    public function uniqueIds(): array
    {
        return [];
    }
}
