<?php

namespace MacropaySolutions\Kernel\Database\Schema;

/**
 * The "mariadb" driver needs the MariaDB client: its mysql* names are deprecated and mysqldump options differ.
 */
class MariaDbSchemaState extends MySqlSchemaState
{
    /**
     * Load the given schema file into the database.
     *
     * @param string $path
     */
    public function load($path): void
    {
        $this->makeProcess(
            'mariadb ' . $this->connectionString() .
                ' --database="${:FRAMEWORK_LOAD_DATABASE}" < "${:FRAMEWORK_LOAD_PATH}"'
        )->setTimeout(null)->mustRun(null, \array_merge($this->baseVariables($this->connection->getConfig()), [
            'FRAMEWORK_LOAD_PATH' => $path,
        ]));
    }

    /**
     * Get the base dump command arguments for MariaDB as a string (no --column-statistics, no GTID option).
     */
    protected function baseDumpCommand(): string
    {
        return 'mariadb-dump ' . $this->connectionString() .
            ' --no-tablespaces --skip-add-locks --skip-comments --skip-set-charset --tz-utc' .
            ' "${:FRAMEWORK_LOAD_DATABASE}"';
    }
}
