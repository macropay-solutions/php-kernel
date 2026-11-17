<?php

namespace MacropaySolutions\Kernel\Tests\Database;

use MacropaySolutions\Kernel\Container\Container;
use MacropaySolutions\Kernel\Database\Connectors\ConnectionFactory;
use MacropaySolutions\Kernel\Database\Connectors\MariaDbConnector;
use MacropaySolutions\Kernel\Database\MariaDbConnection;
use MacropaySolutions\Kernel\Database\Query\Grammars\MariaDbGrammar;
use MacropaySolutions\Kernel\Database\Query\Grammars\MySqlGrammar;
use MacropaySolutions\Kernel\Database\Schema\MariaDbBuilder;
use MacropaySolutions\Kernel\Database\Schema\MariaDbSchemaState;
use MacropaySolutions\Kernel\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class MariaDbDriverTest extends TestCase
{
    public function testTheMariaDbDriverBuildsTheMariaDbClasses(): void
    {
        $factory = new ConnectionFactory(new Container());
        $connection = (new \ReflectionMethod($factory, 'createConnection'))
            ->invoke($factory, 'mariadb', new \PDO('sqlite::memory:'), 'app', '', ['driver' => 'mariadb']);

        $this->assertTrue($connection instanceof MariaDbConnection);
        $this->assertTrue($connection->isMaria());
        $this->assertTrue($connection->getQueryGrammar() instanceof MariaDbGrammar);
        $this->assertTrue($connection->getSchemaBuilder() instanceof MariaDbBuilder);
        $this->assertTrue($connection->getSchemaState(new Filesystem()) instanceof MariaDbSchemaState);
        $this->assertTrue(
            (new \ReflectionMethod($factory, 'createConnector'))->invoke($factory, ['driver' => 'mariadb'])
            instanceof MariaDbConnector
        );
    }

    public function testMariaDbSql(): void
    {
        $connection = new MariaDbConnection(new \PDO('sqlite::memory:'), 'app', '', ['driver' => 'mariadb']);

        $this->assertSame("json_query(?, '$')", $connection->getQueryGrammar()->compileJsonValueCast('?'));
        $this->assertSame(
            'select * from `users` where json_unquote(json_extract(`meta`, \'$."a"\')) = ?',
            $connection->table('users')->where('meta->a', 1)->toSql()
        );

        try {
            $connection->table('users')->joinLateral($connection->table('posts'), 'p')->toSql();
            $this->fail('expected a RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertSame('This database engine does not support lateral joins.', $e->getMessage());
        }
    }

    public function testMariaDbMacrosStayOnTheMariaDbClasses(): void
    {
        MariaDbGrammar::deferredMacro('mariaOnly', [MariaDbDriverTestMacros::class, 'maria']);
        MySqlGrammar::deferredMacro('mysqlToo', [MariaDbDriverTestMacros::class, 'mysql']);

        try {
            $grammar = new MariaDbGrammar();

            $this->assertSame('maria', $grammar->mariaOnly());
            $this->assertSame('mysql', $grammar->mysqlToo());

            // before PHP 8.3 a child's trait does not redeclare the static $macros: it shares the parent's
            if (\PHP_VERSION_ID >= 80300) {
                $this->assertFalse(MySqlGrammar::hasMacro('mariaOnly'));
            }
        } finally {
            MariaDbGrammar::flushMacros();
            MySqlGrammar::flushMacros();
        }
    }

    public function testSchemaDumpAndLoadUseTheMariaDbClient(): void
    {
        $commands = [];
        $state = new MariaDbSchemaState(
            new MariaDbConnection(new \PDO('sqlite::memory:'), 'app', '', ['username' => 'u', 'database' => 'app']),
            new Filesystem(),
            static function (string $command) use (&$commands): Process {
                $commands[] = $command;

                return Process::fromShellCommandline('true');
            }
        );
        $state->load('/tmp/schema.sql');
        $dump = (new \ReflectionMethod($state, 'baseDumpCommand'))->invoke($state);

        $this->assertTrue(\str_starts_with($commands[0], 'mariadb  --user='));
        $this->assertTrue(\str_starts_with($dump, 'mariadb-dump  --user='));
        $this->assertFalse(\str_contains($dump, 'column-statistics'));
        $this->assertFalse(\str_contains($dump, 'set-gtid-purged'));
    }

    public function testStrictModeForMariaDbVersions(): void
    {
        $connector = new MariaDbConnector();
        $mode = (new \ReflectionMethod($connector, 'strictMode'))
            ->invoke($connector, new \PDO('sqlite::memory:'), ['version' => '5.5.5-10.11.6-MariaDB']);

        $this->assertFalse(\str_contains($mode, 'NO_AUTO_CREATE_USER'));
        $this->assertTrue(\str_contains($mode, 'STRICT_TRANS_TABLES'));
    }
}

/**
 * Instance macros are bound to the grammar, so the closures are not static.
 */
class MariaDbDriverTestMacros
{
    public static function maria(): \Closure
    {
        return fn() => 'maria';
    }

    public static function mysql(): \Closure
    {
        return fn() => 'mysql';
    }
}
