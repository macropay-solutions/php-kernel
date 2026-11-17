<?php

namespace MacropaySolutions\Kernel\Tests\Console;

use MacropaySolutions\Kernel\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use PHPUnit\Framework\TestCase;

class CommandConstructionDeprecationsTest extends TestCase
{
    public function testConstructingACommandTriggersNoDeprecation(): void
    {
        $deprecations = [];
        \set_error_handler(static function (int $no, string $message) use (&$deprecations): bool {
            $deprecations[] = $message;

            return true;
        });

        try {
            $commands = [
                new CommandConstructionDeprecationsTestSignature(),
                new CommandConstructionDeprecationsTestName(),
                new CommandConstructionDeprecationsTestAttribute(),
                new CommandConstructionDeprecationsTestNoDescription(),
            ];
        } finally {
            \restore_error_handler();
        }

        $this->assertSame([], $deprecations);
        $this->assertSame(
            [['a:sig', 'Sig', ''], ['a:name', 'Name', ''], ['a:attr', 'Attr', 'Attr help'], ['a:none', '', '']],
            \array_map(static fn(Command $c) => [$c->getName(), $c->getDescription(), $c->getHelp()], $commands)
        );
    }
}

class CommandConstructionDeprecationsTestSignature extends Command
{
    protected $signature = 'a:sig {x?}';
    protected $description = 'Sig';
}

class CommandConstructionDeprecationsTestName extends Command
{
    protected $name = 'a:name';
    protected $description = 'Name';
}

#[AsCommand(name: 'a:attr', description: 'Attr', help: 'Attr help')]
class CommandConstructionDeprecationsTestAttribute extends Command
{
}

class CommandConstructionDeprecationsTestNoDescription extends Command
{
    protected $signature = 'a:none';
}
