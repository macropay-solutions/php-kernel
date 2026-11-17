<?php

namespace MacropaySolutions\Kernel\Database\Connectors;

use PDO;

class MariaDbConnector extends MySqlConnector
{
    /**
     * The MySQL version check does not apply: MariaDB reports versions like "5.5.5-10.11.6-MariaDB".
     *
     * @param \PDO $connection
     * @param array $config
     * @return string
     */
    protected function strictMode(PDO $connection, $config): string
    {
        return "set session sql_mode='ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE," .
            "NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'";
    }
}
