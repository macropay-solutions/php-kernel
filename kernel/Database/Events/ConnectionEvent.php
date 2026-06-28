<?php

namespace MacropaySolutions\Kernel\Database\Events;

abstract class ConnectionEvent
{
    /**
     * The name of the connection.
     *
     * @var string
     */
    public $connectionName;

    /**
     * The database connection instance.
     *
     * @var \MacropaySolutions\Kernel\Database\Connection
     */
    public $connection;

    /**
     * Create a new event instance.
     *
     * @param \MacropaySolutions\Kernel\Database\Connection $connection
     * @return void
     */
    public function __construct($connection)
    {
        $this->connection = $connection;
        $this->connectionName = $connection->getName();
    }
}
