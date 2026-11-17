<?php

namespace MacropaySolutions\Kernel\Database\Query\Grammars;

use MacropaySolutions\Kernel\Database\Query\JoinLateralClause;

class MariaDbGrammar extends MySqlGrammar
{
    use \MacropaySolutions\Framework\Traitables\MacropaySolutionsKernelDatabaseQueryGrammarsMariaDbGrammar;

    /**
     * MariaDB has no lateral joins: fail before sending invalid SQL.
     *
     * @throws \RuntimeException
     */
    public function compileJoinLateral(JoinLateralClause $join, string $expression): string
    {
        throw new \RuntimeException('This database engine does not support lateral joins.');
    }

    /**
     * MariaDB has no "cast(... as json)".
     *
     * @param string $value
     * @return string
     */
    public function compileJsonValueCast($value): string
    {
        return 'json_query(' . $value . ", '$')";
    }
}
