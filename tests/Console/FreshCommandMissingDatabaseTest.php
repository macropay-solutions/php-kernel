<?php

namespace MacropaySolutions\Kernel\Tests\Console;

use MacropaySolutions\Kernel\Container\Container;
use MacropaySolutions\Kernel\Database\Console\Migrations\FreshCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class FreshCommandMissingDatabaseTest extends TestCase
{
    public function testAMissingDatabaseIsNotWipedButMigrated(): void
    {
        $this->assertSame(['migrate'], $this->runFresh(false));
    }

    public function testAnExistingDatabaseIsWipedThenMigrated(): void
    {
        $this->assertSame(['db:wipe', 'migrate'], $this->runFresh(true));
    }

    /**
     * @return string[] the commands fresh called
     */
    private function runFresh(bool $databaseExists): array
    {
        $app = new FreshCommandMissingDatabaseTestApp();
        $app->instance('db', new FreshCommandMissingDatabaseTestManager($databaseExists));
        $command = new FreshCommandMissingDatabaseTestCommand();
        $command->setApp($app);
        $command->run(new ArrayInput(['--force' => true]), new BufferedOutput());

        return $command->called;
    }
}

class FreshCommandMissingDatabaseTestApp extends Container
{
    public function runningUnitTests(): bool
    {
        return true;
    }

    public function isProduction(): bool
    {
        return false;
    }

    public function environment(...$environments): string|bool
    {
        return [] === $environments ? 'testing' : \in_array('testing', $environments, true);
    }
}

class FreshCommandMissingDatabaseTestCommand extends FreshCommand
{
    public array $called = [];

    public function call($command, array $arguments = [])
    {
        $this->called[] = $command;

        return 0;
    }

    public function callSilent($command, array $arguments = [])
    {
        return $this->call($command, $arguments);
    }
}

class FreshCommandMissingDatabaseTestManager
{
    public function __construct(private bool $exists)
    {
    }

    public function connection(?string $name = null): object
    {
        return new class ($this->exists) {
            public function __construct(private bool $exists)
            {
            }

            public function getPdo(): \PDO
            {
                return $this->exists
                    ? new \PDO('sqlite::memory:')
                    : throw new \PDOException("SQLSTATE[HY000] [1049] Unknown database 'app'");
            }
        };
    }
}
