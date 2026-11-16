<?php

namespace MacropaySolutions\Kernel\Tests\Database;

use MacropaySolutions\Kernel\Database\Schema\Grammars\SqlServerGrammar;
use MacropaySolutions\Kernel\Support\Fluent;
use PHPUnit\Framework\TestCase;

/**
 * A string column longer than 4000 characters was created as an invalid nvarchar(n).
 */
class SqlServerLongStringTest extends TestCase
{
    public function testLongStringsUseNvarcharMax(): void
    {
        $grammar = (new \ReflectionClass(SqlServerGrammar::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod($grammar, 'typeString');

        $this->assertSame('nvarchar(255)', $method->invoke($grammar, new Fluent(['length' => 255])));
        $this->assertSame('nvarchar(4000)', $method->invoke($grammar, new Fluent(['length' => 4000])));
        $this->assertSame('nvarchar(max)', $method->invoke($grammar, new Fluent(['length' => 4001])));
    }
}
