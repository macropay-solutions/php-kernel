<?php

namespace MacropaySolutions\Kernel\Tests\Feature\Log;

use MacropaySolutions\Kernel\Tests\ApplicationTestCase;
use MacropaySolutions\Kernel\Tests\Fixtures\Log\RecordLogMessages;

class LoggerListenTest extends ApplicationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        RecordLogMessages::$messages = [];
        $this->app->make('config')->set('logging.channels.null', ['driver' => 'monolog', 'handler' => \Monolog\Handler\NullHandler::class]);
    }

    public function test_listen_registers_a_message_logged_listener_class(): void
    {
        $logger = $this->app->make('log')->channel('null');
        $logger->listen(RecordLogMessages::class);

        $logger->warning('disk almost full');

        $this->assertSame(['warning:disk almost full'], RecordLogMessages::$messages);
    }

    public function test_listen_accepts_an_array_callable(): void
    {
        $logger = $this->app->make('log')->channel('null');
        $logger->listen([RecordLogMessages::class, 'handle']);

        $logger->info('started');

        $this->assertSame(['info:started'], RecordLogMessages::$messages);
    }
}
