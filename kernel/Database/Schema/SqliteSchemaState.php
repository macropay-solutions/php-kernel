<?php

namespace MacropaySolutions\Kernel\Database\Schema;

use MacropaySolutions\Kernel\Database\Connection;

class SqliteSchemaState extends SchemaState
{
    /**
     * Dump the database's schema into a file.
     *
     * @param \MacropaySolutions\Kernel\Database\Connection $connection
     * @param string $path
     * @return void
     */
    public function dump(Connection $connection, $path)
    {
        with(
            $process = $this->makeProcess(
                $this->baseCommand() . ' .schema'
            )
        )->setTimeout(null)->mustRun(
            null,
            array_merge($this->baseVariables($this->connection->getConfig()), [
                //
            ])
        );

        $shadowTables = $this->shadowTables();

        $migrations = collect(preg_split("/\r\n|\n|\r/", $process->getOutput()))->filter(function ($line) use (
            $shadowTables
        ) {
            // internal tables (sqlite_sequence, sqlite_stat1 after ANALYZE...) cannot be created when loading the dump
            return !\preg_match('/^CREATE TABLE (IF NOT EXISTS )?["\'`\[]?sqlite_/i', $line) &&
                stripos($line, 'sqlite_sequence') === false &&
                !$this->isShadowTableLine($line, $shadowTables) &&
                strlen($line) > 0;
        })->all();

        $this->files->put($path, implode(PHP_EOL, $migrations) . PHP_EOL);

        if ($this->hasMigrationTable()) {
            $this->appendMigrationData($path);
        }
    }

    /**
     * The tables a virtual table (FTS5...) creates itself: loading the dump creates them with the virtual table,
     * so their own CREATE TABLE fails with "table already exists".
     *
     * @return string[]
     */
    protected function shadowTables(): array
    {
        try {
            $tables = $this->connection->selectFromWriteConnection('pragma main.table_list');
        } catch (\Throwable) {
            // pragma table_list needs SQLite 3.37
            return [];
        }

        return collect($tables)->where('type', 'shadow')->pluck('name')->all();
    }

    protected function isShadowTableLine(string $line, array $shadowTables): bool
    {
        foreach ($shadowTables as $name) {
            $quoted = \preg_quote($name, '/');

            if (\preg_match('/^CREATE TABLE (IF NOT EXISTS )?["\'`\[]?' . $quoted . '["\'`\]]?\s*\(/i', $line)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Append the migration data to the schema dump.
     *
     * @param string $path
     * @return void
     */
    protected function appendMigrationData(string $path)
    {
        with(
            $process = $this->makeProcess(
                $this->baseCommand() . ' ".dump \'' . $this->migrationTable . '\'"'
            )
        )->mustRun(
            null,
            array_merge($this->baseVariables($this->connection->getConfig()), [
                //
            ])
        );

        $migrations = collect(preg_split("/\r\n|\n|\r/", $process->getOutput()))->filter(function ($line) {
            return preg_match('/^\s*(--|INSERT\s)/iu', $line) === 1 &&
                strlen($line) > 0;
        })->all();

        $this->files->append($path, implode(PHP_EOL, $migrations) . PHP_EOL);
    }

    /**
     * Load the given schema file into the database.
     *
     * @param string $path
     * @return void
     */
    public function load($path)
    {
        if ($this->connection->getDatabaseName() === ':memory:') {
            $this->connection->getPdo()->exec($this->files->get($path));

            return;
        }

        $process = $this->makeProcess($this->baseCommand() . ' < "${:FRAMEWORK_LOAD_PATH}"');

        $process->mustRun(null, array_merge($this->baseVariables($this->connection->getConfig()), [
            'FRAMEWORK_LOAD_PATH' => $path,
        ]));
    }

    /**
     * Get the base sqlite command arguments as a string.
     *
     * @return string
     */
    protected function baseCommand()
    {
        return 'sqlite3 "${:FRAMEWORK_LOAD_DATABASE}"';
    }

    /**
     * Get the base variables for a dump / load command.
     *
     * @param array $config
     * @return array
     */
    protected function baseVariables(array $config)
    {
        return [
            'FRAMEWORK_LOAD_DATABASE' => $config['database'],
        ];
    }
}
