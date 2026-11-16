<?php

namespace MacropaySolutions\Kernel\Tests\Database;

use MacropaySolutions\Kernel\Database\Query\Processors\MySqlProcessor;
use MacropaySolutions\Kernel\Database\Query\Processors\PostgresProcessor;
use MacropaySolutions\Kernel\Database\Query\Processors\SQLiteProcessor;
use MacropaySolutions\Kernel\Database\Query\Processors\SqlServerProcessor;
use PHPUnit\Framework\TestCase;

/**
 * An expression index (e.g. on lower(email)) has no column names: explode() got null, which is deprecated and gave
 * [''] as the columns.
 */
class ProcessIndexesExpressionTest extends TestCase
{
    public function testAnIndexWithoutColumnNamesHasNoColumns(): void
    {
        $row = ['name' => 'Users_Lower_Email', 'columns' => null, 'type' => 'btree', 'unique' => 1, 'primary' => 0];

        $processors = [new PostgresProcessor(), new MySqlProcessor(), new SQLiteProcessor(), new SqlServerProcessor()];

        foreach ($processors as $processor) {
            $this->assertSame([], $processor->processIndexes([$row])[0]['columns'], $processor::class);
        }

        $named = ['columns' => 'a,b'] + $row;

        $this->assertSame(['a', 'b'], (new PostgresProcessor())->processIndexes([$named])[0]['columns']);
    }
}
