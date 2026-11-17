<?php

namespace MacropaySolutions\Kernel\Tests\Console;

use MacropaySolutions\Kernel\Console\Application;
use MacropaySolutions\Kernel\Console\Command;
use MacropaySolutions\Kernel\Console\OutputStyle;
use MacropaySolutions\Kernel\Console\Scheduling\Event;
use MacropaySolutions\Kernel\Console\Scheduling\EventMutex;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use PHPUnit\Framework\TestCase;

class ConsoleOutputAndMutexTest extends TestCase
{
    public function testAHiddenLineWritesNoNewline(): void
    {
        $style = new OutputStyle(new ArrayInput([]), new BufferedOutput());
        $style->write('x');
        $style->writeln('hidden', OutputInterface::VERBOSITY_VERBOSE);

        $this->assertSame(0, $style->newLinesWritten());

        $style->writeln('shown');

        $this->assertSame(1, $style->newLinesWritten());
    }

    public function testTheMutexNameDoesNotDependOnThePhpBinaryPath(): void
    {
        $event = new Event($this->createStub(EventMutex::class), Application::formatCommandString('inspire'));

        $this->assertSame(
            'framework' . DIRECTORY_SEPARATOR . 'schedule-' . \sha1('* * * * *php run inspire'),
            $event->mutexName()
        );
    }

    public function testTheAttributeHelpIsKept(): void
    {
        $this->assertSame('Attribute help', (new ConsoleOutputAndMutexTestCommand())->getHelp());
    }
}

#[AsCommand(name: 'test:help', description: 'Description', help: 'Attribute help')]
class ConsoleOutputAndMutexTestCommand extends Command
{
    public function handle(): void
    {
    }
}
