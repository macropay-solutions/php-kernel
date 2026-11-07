<?php

namespace MacropaySolutions\Kernel\Tests\Feature\Console;

use MacropaySolutions\Kernel\Tests\Fixtures\CachedApp\CachedApplication;
use PHPUnit\Framework\TestCase;

/**
 * config:cache, route:cache, autowiring:cache, commands:cache and merge-cached-files:cache on an app skeleton,
 * then a fresh app booted from the cache files. Every step runs in its own PHP process, like separate deploys
 * and requests.
 */
class CachedBootTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = \sys_get_temp_dir() . '/cached-boot-' . \bin2hex(\random_bytes(4));
        \mkdir($this->dir . '/bootstrap/cache', 0777, true);
        \mkdir($this->dir . '/config');
        \mkdir($this->dir . '/storage/framework/views', 0777, true);
        $this->writeConfig('from-file');

        $autoload = \var_export(\dirname(__DIR__, 3) . '/vendor/autoload.php', true);
        $application = CachedApplication::class;

        \file_put_contents($this->dir . '/bootstrap/app.php', <<<PHP
<?php

require_once {$autoload};

\$app = new \\{$application}(\\dirname(__DIR__));
\$app->singleton(
    \\MacropaySolutions\\Kernel\\Contracts\\Debug\\ExceptionHandler::class,
    \\MacropaySolutions\\Framework\\Exceptions\\Handler::class
);
\$app->singleton(
    \\MacropaySolutions\\Kernel\\Contracts\\Console\\Kernel::class,
    \\MacropaySolutions\\Framework\\Console\\Kernel::class
);

if (!\$app->configurationIsCached()) {
    \$app->configure('app');
    \$app->configure('probe');
}

if (!\$app->routesAreCached()) {
    \$app->router->registerRoutes();
}

return \$app->boot();
PHP);
    }

    protected function tearDown(): void
    {
        \exec('rm -rf ' . \escapeshellarg($this->dir));

        parent::tearDown();
    }

    public function test_without_caches_the_app_reads_config_and_registers_routes(): void
    {
        $this->assertSame([
            'configCached' => false,
            'routesCached' => false,
            'probe' => 'from-file',
            'responses' => ['/status' => [200, 'from-file'], '/items/5' => [200, 'item:5'], '/items/abc' => [404, '']],
            'bound' => [false, false, false],
            'commandsCached' => false,
            'queueWorkListed' => true,
        ], $this->runStep('serve'));
    }

    public function test_a_fresh_app_boots_from_the_config_and_route_caches(): void
    {
        $this->assertSame(
            [
                'config:cache' => 0,
                'route:cache' => 0,
                'autowiring:cache' => 0,
                'commands:cache' => 0,
                'merge-cached-files:cache' => 0,
            ],
            $this->runStep('cache')
        );
        $this->assertFileExists($this->dir . '/bootstrap/cache/config.php');
        $this->assertFileExists($this->dir . '/bootstrap/cache/routes-v8.php');
        $this->assertDirectoryExists($this->dir . '/bootstrap/cache/autowiring');

        // The config file changes after caching: the app keeps serving the cached value.
        $this->writeConfig('changed-after-cache');

        $this->assertSame([
            'configCached' => true,
            'routesCached' => true,
            'probe' => 'from-file',
            'responses' => ['/status' => [200, 'from-file'], '/items/5' => [200, 'item:5'], '/items/abc' => [404, '']],
            'bound' => [false, false, false],
            'commandsCached' => true,
            'queueWorkListed' => true,
        ], $this->runStep('serve'));
    }

    public function test_the_merged_cache_file_is_enough_on_its_own(): void
    {
        $this->runStep('cache');
        $this->assertFileExists($this->dir . '/bootstrap/cache/merged-cached-files.php');

        \unlink($this->dir . '/bootstrap/cache/config.php');
        \unlink($this->dir . '/bootstrap/cache/routes-v8.php');
        $this->writeConfig('changed-after-cache');

        $result = $this->runStep('serve');

        $this->assertTrue($result['configCached']);
        $this->assertTrue($result['routesCached']);
        $this->assertSame('from-file', $result['probe']);
        $this->assertSame([200, 'item:5'], $result['responses']['/items/5']);
    }

    public function test_pre_registering_every_binding_keeps_requests_working(): void
    {
        $this->runStep('cache');

        $result = $this->runStep('preregister');

        $this->assertSame([true, true, true], $result['bound']);
        $this->assertSame([200, 'from-file'], $result['responses']['/status']);
        $this->assertSame([200, 'item:5'], $result['responses']['/items/5']);
    }

    private function writeConfig(string $value): void
    {
        \file_put_contents(
            $this->dir . '/config/probe.php',
            '<?php return ' . \var_export(['value' => $value], true) . ';'
        );
        \clearstatcache();

        if (\function_exists('opcache_invalidate')) {
            \opcache_invalidate($this->dir . '/config/probe.php', true);
        }
    }

    private function runStep(string $step): array
    {
        $command = \escapeshellarg(\PHP_BINARY) . ' '
            . \escapeshellarg(\dirname(__DIR__, 2) . '/Fixtures/CachedApp/cached-app.php') . ' '
            . \escapeshellarg($step) . ' ' . \escapeshellarg($this->dir) . ' 2>&1';

        \exec($command, $lines, $exitCode);
        $output = \implode("\n", $lines);

        $this->assertSame(0, $exitCode, $output);

        $decoded = \json_decode($output, true);

        $this->assertIsArray($decoded, $output);

        return $decoded;
    }
}
