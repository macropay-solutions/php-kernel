<?php

namespace MacropaySolutions\Kernel\Tests\Database;

use MacropaySolutions\Kernel\Database\SQLiteConnection;
use PHPUnit\Framework\TestCase;

class DisconnectDeadConnectionTest extends TestCase
{
    public function testTheQuitWarningOfADeadConnectionDoesNotEscapeDisconnect(): void
    {
        $connection = new SQLiteConnection(new \PDO('sqlite::memory:'));
        $connection->setPdo(new DisconnectDeadConnectionTestPdo())->setReadPdo(new DisconnectDeadConnectionTestPdo());
        // as in the app: its error handler turns warnings into exceptions
        \set_error_handler(static function (int $level, string $message): bool {
            throw new \ErrorException($message, 0, $level);
        });

        try {
            $connection->disconnect();
        } finally {
            \restore_error_handler();
        }

        $this->assertNull($connection->getRawPdo());
        $this->assertNull($connection->getRawReadPdo());
    }
}

/**
 * Warns on destruct like PDO does when the server has gone away.
 */
class DisconnectDeadConnectionTestPdo
{
    public function __destruct()
    {
        \trigger_error('PDO::__destruct(): Error while sending QUIT packet', E_USER_WARNING);
    }
}
