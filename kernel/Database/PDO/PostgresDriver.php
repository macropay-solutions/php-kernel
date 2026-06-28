<?php

namespace MacropaySolutions\Kernel\Database\PDO;

use Doctrine\DBAL\Driver\AbstractPostgreSQLDriver;
use MacropaySolutions\Kernel\Database\PDO\Concerns\ConnectsToDatabase;

class PostgresDriver extends AbstractPostgreSQLDriver
{
    use ConnectsToDatabase;

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'pdo_pgsql';
    }
}
