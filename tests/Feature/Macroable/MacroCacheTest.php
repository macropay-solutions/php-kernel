<?php

namespace MacropaySolutions\Kernel\Tests\Feature\Macroable;

use PHPUnit\Framework\TestCase;

/**
 * macro:cache compiles deferred macros into native methods of each class's dedicated trait. The compile and the
 * call each run in their own PHP process, because a trait is fixed once its class is loaded.
 */
class MacroCacheTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $root = \dirname(__DIR__, 3);
        $classMap = require $root . '/vendor/composer/autoload_classmap.php';

        if (!isset($classMap['MacropaySolutions\\Kernel\\Support\\Collection'])) {
            $this->markTestSkipped('macro:cache reads the classmap: run composer with --optimize-autoloader.');
        }

        $this->dir = \sys_get_temp_dir() . '/macro-cache-' . \bin2hex(\random_bytes(4));
        \mkdir($this->dir . '/bootstrap/cache', 0777, true);
        \symlink($root . '/vendor', $this->dir . '/vendor');
    }

    protected function tearDown(): void
    {
        if (isset($this->dir)) {
            \exec('rm -rf ' . \escapeshellarg($this->dir));
        }

        parent::tearDown();
    }

    public function test_deferred_macros_are_compiled_into_native_methods(): void
    {
        [$exitCode, $output] = $this->runStep('compile');

        $this->assertSame(0, $exitCode, $output);

        $trait = $this->dir . '/bootstrap/cache/traitables/MacropaySolutionsKernelSupportCollection.php';

        $this->assertFileExists($trait);
        $compiled = (string)\file_get_contents($trait);

        $this->assertStringContainsString('public function doubled(int $times = 2): \\', $compiled);
        $this->assertStringContainsString('public static function fromRange(int $from, int $to)', $compiled);

        [$exitCode, $output] = $this->runStep('call');

        $this->assertSame(0, $exitCode, $output);
        // Compiled macros are real methods, so the compiled trait reports no runtime macros.
        $this->assertSame(
            ['native' => true, 'doubled' => [3, 6, 9], 'fromRange' => [2, 3, 4], 'hasMacro' => false],
            \json_decode($output, true)
        );
    }

    /**
     * @return array{int, string}
     */
    private function runStep(string $step): array
    {
        $command = \escapeshellarg(\PHP_BINARY) . ' '
            . \escapeshellarg(\dirname(__DIR__, 2) . '/Fixtures/Macros/macro-cache.php') . ' '
            . \escapeshellarg($step) . ' ' . \escapeshellarg($this->dir) . ' 2>&1';

        \exec($command, $lines, $exitCode);

        return [$exitCode, \implode("\n", $lines)];
    }
}
