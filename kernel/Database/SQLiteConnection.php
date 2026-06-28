<?php

namespace MacropaySolutions\Kernel\Database;

use Exception;
use MacropaySolutions\Kernel\Database\PDO\SQLiteDriver;
use MacropaySolutions\Kernel\Database\Query\Grammars\SQLiteGrammar as QueryGrammar;
use MacropaySolutions\Kernel\Database\Query\Processors\SQLiteProcessor;
use MacropaySolutions\Kernel\Database\Schema\Grammars\SQLiteGrammar as SchemaGrammar;
use MacropaySolutions\Kernel\Database\Schema\SQLiteBuilder;
use MacropaySolutions\Kernel\Database\Schema\SqliteSchemaState;
use MacropaySolutions\Kernel\Filesystem\Filesystem;

class SQLiteConnection extends Connection
{
    /**
     * Create a new database connection instance.
     *
     * @param \PDO|\Closure $pdo
     * @param string $database
     * @param string $tablePrefix
     * @param array $config
     * @return void
     */
    public function __construct($pdo, $database = '', $tablePrefix = '', array $config = [])
    {
        parent::__construct($pdo, $database, $tablePrefix, $config);

        $enableForeignKeyConstraints = $this->getForeignKeyConstraintsConfigurationValue();

        if ($enableForeignKeyConstraints === null) {
            return;
        }

        $enableForeignKeyConstraints
            ? $this->getSchemaBuilder()->enableForeignKeyConstraints()
            : $this->getSchemaBuilder()->disableForeignKeyConstraints();
    }

    /**
     * Escape a binary value for safe SQL embedding.
     *
     * @param string $value
     * @return string
     */
    protected function escapeBinary($value)
    {
        $hex = bin2hex($value);

        return "x'{$hex}'";
    }

    /**
     * Determine if the given database exception was caused by a unique constraint violation.
     *
     * @param \Exception $exception
     * @return bool
     */
    protected function isUniqueConstraintError(Exception $exception)
    {
        return boolval(
            preg_match('#(column(s)? .* (is|are) not unique|UNIQUE constraint failed: .*)#i', $exception->getMessage())
        );
    }

    /**
     * Get the default query grammar instance.
     *
     * @return \MacropaySolutions\Kernel\Database\Query\Grammars\SQLiteGrammar
     */
    protected function getDefaultQueryGrammar()
    {
        ($grammar = new QueryGrammar())->setConnection($this);

        return $this->withTablePrefix($grammar);
    }

    /**
     * Get a schema builder instance for the connection.
     *
     * @return \MacropaySolutions\Kernel\Database\Schema\SQLiteBuilder
     */
    public function getSchemaBuilder()
    {
        if (is_null($this->schemaGrammar)) {
            $this->useDefaultSchemaGrammar();
        }

//        return new SQLiteBuilder($this);
        return \di(SQLiteBuilder::class, [$this]);
    }

    /**
     * Get the default schema grammar instance.
     *
     * @return \MacropaySolutions\Kernel\Database\Schema\Grammars\SQLiteGrammar
     */
    protected function getDefaultSchemaGrammar()
    {
        ($grammar = new SchemaGrammar())->setConnection($this);

        return $this->withTablePrefix($grammar);
    }

    /**
     * Get the schema state for the connection.
     *
     * @param \MacropaySolutions\Kernel\Filesystem\Filesystem|null $files
     * @param callable|null $processFactory
     *
     * @throws \RuntimeException
     */
    public function getSchemaState(?Filesystem $files = null, ?callable $processFactory = null)
    {
        return new SqliteSchemaState($this, $files, $processFactory);
    }

    /**
     * Get the default post processor instance.
     *
     * @return \MacropaySolutions\Kernel\Database\Query\Processors\SQLiteProcessor
     */
    protected function getDefaultPostProcessor()
    {
        return new SQLiteProcessor();
    }

    /**
     * Get the Doctrine DBAL driver.
     *
     * @return \MacropaySolutions\Kernel\Database\PDO\SQLiteDriver
     */
    protected function getDoctrineDriver()
    {
        return new SQLiteDriver();
    }

    /**
     * Get the database connection foreign key constraints configuration option.
     *
     * @return bool|null
     */
    protected function getForeignKeyConstraintsConfigurationValue()
    {
        return $this->getConfig('foreign_key_constraints');
    }
}
