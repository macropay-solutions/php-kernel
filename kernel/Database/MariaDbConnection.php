<?php

namespace MacropaySolutions\Kernel\Database;

use MacropaySolutions\Kernel\Database\Query\Grammars\MariaDbGrammar as QueryGrammar;
use MacropaySolutions\Kernel\Database\Query\Processors\MariaDbProcessor;
use MacropaySolutions\Kernel\Database\Schema\Grammars\MariaDbGrammar as SchemaGrammar;
use MacropaySolutions\Kernel\Database\Schema\MariaDbBuilder;
use MacropaySolutions\Kernel\Database\Schema\MariaDbSchemaState;
use MacropaySolutions\Kernel\Filesystem\Filesystem;

class MariaDbConnection extends MySqlConnection
{
    use \MacropaySolutions\Framework\Traitables\MacropaySolutionsKernelDatabaseMariaDbConnection;

    /**
     * The "mariadb" driver is always a MariaDB server: no server version lookup.
     */
    public function isMaria(): bool
    {
        return true;
    }

    protected function getDefaultQueryGrammar(): QueryGrammar
    {
        ($grammar = new QueryGrammar())->setConnection($this);

        return $this->withTablePrefix($grammar);
    }

    public function getSchemaBuilder(): MariaDbBuilder
    {
        if (null === $this->schemaGrammar) {
            $this->useDefaultSchemaGrammar();
        }

        return \di(MariaDbBuilder::class, [$this]);
    }

    protected function getDefaultSchemaGrammar(): SchemaGrammar
    {
        ($grammar = new SchemaGrammar())->setConnection($this);

        return $this->withTablePrefix($grammar);
    }

    public function getSchemaState(?Filesystem $files = null, ?callable $processFactory = null): MariaDbSchemaState
    {
        return new MariaDbSchemaState($this, $files, $processFactory);
    }

    protected function getDefaultPostProcessor(): MariaDbProcessor
    {
        return new MariaDbProcessor();
    }
}
