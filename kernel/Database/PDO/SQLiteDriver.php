<?php

namespace MacropaySolutions\Kernel\Database\PDO;

use Doctrine\DBAL\Driver\AbstractSQLiteDriver;
use MacropaySolutions\Kernel\Database\PDO\Concerns\ConnectsToDatabase;

class SQLiteDriver extends AbstractSQLiteDriver
{
    use ConnectsToDatabase;

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'pdo_sqlite';
    }
}
