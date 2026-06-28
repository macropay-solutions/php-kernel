<?php

namespace MacropaySolutions\Kernel\Database\Obvious;

use LogicException;
use MacropaySolutions\Kernel\Database\Events\ModelsPruned;

trait MassPrunable
{
    /**
     * Prune all prunable models in the database.
     *
     * @param int $chunkSize
     * @return int
     */
    public function pruneAll(int $chunkSize = 1000)
    {
        $query = tap($this->prunable(), function ($query) use ($chunkSize) {
            $query->when(!$query->getQuery()->limit, function ($query) use ($chunkSize) {
                $query->limit($chunkSize);
            });
        });

        $total = 0;

        do {
            $total += $count = in_array(SoftDeletes::class, class_uses_recursive(static::class))
                ? $query->forceDelete()
                : $query->delete();

            if ($count > 0) {
                event(new ModelsPruned(static::class, $total));
            }
        } while ($count > 0);

        return $total;
    }

    /**
     * Get the prunable model query.
     *
     * @return \MacropaySolutions\Kernel\Database\Obvious\Builder
     */
    public function prunable()
    {
        throw new LogicException('Please implement the prunable method on your model.');
    }
}
