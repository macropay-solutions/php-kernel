<?php

namespace MacropaySolutions\Kernel\Database\Concerns;

use MacropaySolutions\Kernel\Support\Collection;

trait ExplainsQueries
{
    /**
     * Explains the query.
     *
     * @return \MacropaySolutions\Kernel\Support\Collection
     */
    public function explain()
    {
        $sql = $this->toSql();

        $bindings = $this->getBindings();

        $explanation = $this->getConnection()->select('EXPLAIN ' . $sql, $bindings);

//        return new Collection($explanation);
        return \di(Collection::class, [$explanation]);
    }
}
