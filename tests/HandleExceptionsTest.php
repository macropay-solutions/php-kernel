<?php

use MacropaySolutions\Kernel\Config\Repository as Config;
use MacropaySolutions\Kernel\Container\Container;
use MacropaySolutions\Kernel\Log\LogManager;
use MacropaySolutions\Framework\Concerns\RegistersExceptionHandlers;
use Mockery as m;
use Monolog\Handler\NullHandler;
use PHPUnit\Framework\TestCase;

class HandleExceptionsTest extends TestCase
{
    use RegistersExceptionHandlers;

    protected $container;
    protected $config;

    protected function setUp(): void
    {
        parent::setUp();

        // CRITICAL: Ensure error_reporting is open so handleError() doesn't skip logic
        error_reporting(-1);

        $this->container = new Container();
        Container::setInstance($this->container);

        $this->config = new Config();
        $this->container->singleton('config', fn() => $this->config);
    }

    protected function tearDown(): void
    {
        // We do NOT call restore_error_handler here because this test class
        // doesn't boot the app; calling it would remove PHPUnit's own handlers.
        $this->container::setInstance(null);
        m::close();
        parent::tearDown();
    }

    /**
     * Missing methods the trait expects from the "Application"
     */
    protected function bound($abstract)
    {
        return $this->container->bound($abstract);
    }

    protected function runningInConsole()
    {
        return false;
    }

    public function testPhpDeprecations()
    {
        $logger = m::mock(LogManager::class);
        $this->container->instance('log', $logger);
        $logger->shouldReceive('channel')->with('deprecations')->andReturnSelf();
        $logger->shouldReceive('warning')->once();

        $this->handleError(E_DEPRECATED, 'deprecated', 'file.php', 1);
        $this->assertTrue(true);
    }

    public function testUserDeprecations()
    {
        $logger = m::mock(LogManager::class);
        $this->container->instance('log', $logger);
        $logger->shouldReceive('channel')->with('deprecations')->andReturnSelf();
        $logger->shouldReceive('warning')->once();

        $this->handleError(E_USER_DEPRECATED, 'deprecated', 'file.php', 1);
        $this->assertTrue(true);
    }

    public function testErrors()
    {
        $this->expectException(ErrorException::class);
        $this->handleError(E_ERROR, 'Something went wrong', 'file.php', 1);
    }

    public function testEnsuresDeprecationsDriver()
    {
        $logger = m::mock(LogManager::class);
        $this->container->instance('log', $logger);
        $logger->shouldReceive('channel')->andReturnSelf();
        $logger->shouldReceive('warning');

        $this->config->set('logging.channels.stack', [
            'driver' => 'stack',
            'channels' => ['single'],
        ]);
        $this->config->set('logging.deprecations', 'stack');

        $this->handleError(E_USER_DEPRECATED, 'deprecated', 'file.php', 1);

        $this->assertEquals(
            ['driver' => 'stack', 'channels' => ['single']],
            $this->config->get('logging.channels.deprecations')
        );
    }

    public function testEnsuresNullDeprecationsDriver()
    {
        $logger = m::mock(LogManager::class);
        $this->container->instance('log', $logger);
        $logger->shouldReceive('channel')->andReturnSelf();
        $logger->shouldReceive('warning');

        $this->config->set('logging.channels.null', [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ]);
        $this->config->set('logging.deprecations', 'null');

        $this->handleError(E_USER_DEPRECATED, 'deprecated', 'file.php', 1);

        $this->assertEquals(
            NullHandler::class,
            $this->config->get('logging.channels.deprecations.handler')
        );
    }

    public function testNoDeprecationsDriverIfNoDeprecationsHereSend()
    {
        $this->assertNull($this->config->get('logging.deprecations'));
        $this->assertNull($this->config->get('logging.channels.deprecations'));
    }

    public function testIgnoreDeprecationIfLoggerUnresolvable()
    {
        // Mock a failure to resolve 'log' to trigger the catch block in the trait
        $this->container->singleton('log', function() { throw new Exception; });

        $this->handleError(E_DEPRECATED, 'deprecated', 'file.php', 1);
        $this->assertTrue(true);
    }

    protected function make($abstract, array $parameters = [])
    {
        return $this->container->make($abstract, $parameters);
    }
}
