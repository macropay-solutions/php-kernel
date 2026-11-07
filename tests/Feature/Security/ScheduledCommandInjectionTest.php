<?php

namespace MacropaySolutions\Kernel\Tests\Feature\Security;

use MacropaySolutions\Kernel\Console\Scheduling\Schedule;
use MacropaySolutions\Kernel\Support\ProcessUtils;
use MacropaySolutions\Kernel\Tests\ApplicationTestCase;

/**
 * Scheduled commands are run through the shell, so parameters, the output path and the user must reach it as
 * single arguments. Each test runs the built command and checks that the injected command did not run.
 */
class ScheduledCommandInjectionTest extends ApplicationTestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        if (\DIRECTORY_SEPARATOR === '\\') {
            $this->markTestSkipped('The shell assertions are POSIX only.');
        }

        $this->dir = \sys_get_temp_dir() . '/schedule-injection-' . \bin2hex(\random_bytes(4));
        \mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (\glob($this->dir . '/*') ?: [] as $file) {
            \unlink($file);
        }

        @\rmdir($this->dir);

        parent::tearDown();
    }

    public function test_positional_parameters_reach_the_command_as_one_argument(): void
    {
        $output = $this->runScheduled('printf %s', ['a; touch ' . $this->marker()]);

        $this->assertSame('a; touch ' . $this->marker(), $output);
        $this->assertFileDoesNotExist($this->marker());
    }

    public function test_option_values_reach_the_command_as_one_argument(): void
    {
        $output = $this->runScheduled('printf %s', ['--name=x; touch ' . $this->marker()]);

        $this->assertSame('--name=x; touch ' . $this->marker(), $output);
        $this->assertFileDoesNotExist($this->marker());
    }

    public function test_keyed_option_values_reach_the_command_as_one_argument(): void
    {
        $output = $this->runScheduled('printf %s', ['--name' => '--x; touch ' . $this->marker()]);

        $this->assertSame('--name=--x; touch ' . $this->marker(), $output);
        $this->assertFileDoesNotExist($this->marker());
    }

    public function test_bare_flags_are_passed_unquoted(): void
    {
        $event = $this->app->make(Schedule::class)->exec('report', ['-v', '--force', 'all', 5]);

        $this->assertSame("report -v --force 'all' 5", $event->command);
    }

    public function test_the_output_path_is_one_argument(): void
    {
        $outputFile = $this->dir . '/out$(printf pwned)';
        $event = $this->app->make(Schedule::class)->exec('printf ok')->sendOutputTo($outputFile);

        \shell_exec($event->buildCommand());

        $this->assertFileDoesNotExist($this->dir . '/outpwned');
        $this->assertSame('ok', \file_get_contents($outputFile));
    }

    public function test_the_user_is_quoted_in_the_sudo_prefix(): void
    {
        $event = $this->app->make(Schedule::class)->exec('printf ok')->user('root; touch /tmp/x');

        $this->assertStringStartsWith("sudo -u 'root; touch /tmp/x' -- sh -c ", $event->buildCommand());
    }

    public function test_nul_bytes_are_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ProcessUtils::escapeArgument("a\0b");
    }

    private function runScheduled(string $command, array $parameters): string
    {
        $outputFile = $this->dir . '/output';
        $event = $this->app->make(Schedule::class)->exec($command, $parameters)->sendOutputTo($outputFile);

        \shell_exec($event->buildCommand());

        return (string)\file_get_contents($outputFile);
    }

    private function marker(): string
    {
        return $this->dir . '/pwned';
    }
}
