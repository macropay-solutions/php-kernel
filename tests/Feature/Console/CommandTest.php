<?php

namespace MacropaySolutions\Kernel\Tests\Feature\Console;

use MacropaySolutions\Kernel\Contracts\Console\Kernel as ConsoleKernelContract;
use MacropaySolutions\Kernel\Tests\ApplicationTestCase;
use MacropaySolutions\Kernel\Tests\Fixtures\Console\TestConsoleKernel;

/**
 * Commands registered on the console kernel, run with arguments and options as `php run` does.
 */
class CommandTest extends ApplicationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->singleton(ConsoleKernelContract::class, TestConsoleKernel::class);
    }

    public function test_arguments_and_default_options(): void
    {
        $this->assertSame(0, $this->artisan('test:greet', ['name' => 'Ana']));
        $this->assertSame("Hello Ana\n", $this->artisanOutput());
    }

    public function test_array_arguments_and_options(): void
    {
        $exitCode = $this->artisan('test:greet', [
            'name' => 'Ana',
            'titles' => ['Dr.', 'Prof.'],
            '--times' => 2,
            '--shout' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertSame("HELLO DR. PROF. ANA\nHELLO DR. PROF. ANA\n", $this->artisanOutput());
    }

    public function test_a_failing_command_returns_its_exit_code(): void
    {
        $this->assertSame(1, $this->artisan('test:greet', ['name' => 'nobody']));
        $this->assertStringContainsString('Nobody to greet.', $this->artisanOutput());
    }

    public function test_a_command_can_call_another_command(): void
    {
        $this->assertSame(0, $this->artisan('test:chain'));
        $this->assertSame("HELLO CHAINED\n", $this->artisanOutput());
    }

    public function test_a_missing_required_argument_is_rejected(): void
    {
        $this->expectException(\Symfony\Component\Console\Exception\RuntimeException::class);
        $this->expectExceptionMessage('Not enough arguments (missing: "name").');

        $this->artisan('test:greet');
    }

    public function test_registered_commands_are_listed(): void
    {
        $commands = $this->app->make(ConsoleKernelContract::class)->all();

        $this->assertArrayHasKey('test:greet', $commands);
        $this->assertArrayHasKey('queue:work', $commands);
        $this->assertSame('Greets someone', $commands['test:greet']->getDescription());
    }

    public function test_cache_commands_work_on_the_configured_store(): void
    {
        $cache = $this->app->make('cache');
        $cache->put('framework', 'value', 60);
        $cache->put('other', 'value', 60);

        $this->assertSame(0, $this->artisan('cache:forget', ['key' => 'framework']));
        $this->assertNull($cache->get('framework'));
        $this->assertSame('value', $cache->get('other'));

        $this->assertSame(0, $this->artisan('cache:clear'));
        $this->assertNull($cache->get('other'));
    }
}
