<?php

namespace MacropaySolutions\Kernel\Database\PDO;

use Doctrine\DBAL\Driver\AbstractMySQLDriver;
use MacropaySolutions\Kernel\Database\PDO\Concerns\ConnectsToDatabase;

class MySqlDriver extends AbstractMySQLDriver
{
    use ConnectsToDatabase;

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'pdo_mysql';
    }
}
