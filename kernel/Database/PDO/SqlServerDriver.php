<?php

namespace MacropaySolutions\Kernel\Database\PDO;

use Doctrine\DBAL\Driver\AbstractSQLServerDriver;

class SqlServerDriver extends AbstractSQLServerDriver
{
    /**
     * Create a new database connection.
     *
     * @param mixed[] $params
     * @param string|null $username
     * @param string|null $password
     * @param mixed[] $driverOptions
     * @return \MacropaySolutions\Kernel\Database\PDO\SqlServerConnection
     */
    public function connect(array $params, $username = null, $password = null, array $driverOptions = [])
    {
//        return new SqlServerConnection(
        return \di(SqlServerConnection::class, [
//            new Connection($params['pdo'])
            \di(Connection::class, [$params['pdo']]),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'pdo_sqlsrv';
    }
}
