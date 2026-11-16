<?php

namespace MacropaySolutions\Kernel\Tests\Log;

use MacropaySolutions\Kernel\Config\Repository;
use MacropaySolutions\Kernel\Container\Container;
use MacropaySolutions\Kernel\Events\Dispatcher;
use MacropaySolutions\Kernel\Log\Logger;
use MacropaySolutions\Kernel\Log\LogManager;
use Monolog\Handler\NullHandler;
use PHPUnit\Framework\TestCase;

/**
 * Log::build() ignored the 'tap' of the given configuration: the taps were read from the (missing) "ondemand"
 * channel configuration.
 */
class LogBuildTapTest extends TestCase
{
    public function testTheTapsOfTheBuiltChannelAreApplied(): void
    {
        $app = new Container();
        $app->instance('events', new Dispatcher($app));
        $app->instance('config', new Repository(['logging' => ['channels' => []]]));
        LogBuildTapTestTap::$tapped = [];

        (new LogManager($app))->build([
            'driver' => 'monolog',
            'handler' => NullHandler::class,
            'tap' => [LogBuildTapTestTap::class . ':a,b'],
        ]);

        $this->assertSame([['a', 'b']], LogBuildTapTestTap::$tapped);
    }
}

class LogBuildTapTestTap
{
    public static array $tapped = [];

    public function __invoke(Logger $logger, string ...$arguments): void
    {
        self::$tapped[] = $arguments;
    }
}
