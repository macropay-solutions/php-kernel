<?php

namespace MacropaySolutions\Kernel\Tests\Database;

use MacropaySolutions\Kernel\Database\DetectsLostConnections;
use PHPUnit\Framework\TestCase;

/**
 * A connect timeout reported as "Operation in progress" was not seen as a lost connection, so it was not retried.
 */
class LostConnectionMessagesTest extends TestCase
{
    use DetectsLostConnections;

    public function testOperationInProgressIsALostConnection(): void
    {
        $timeout = new \PDOException('SQLSTATE[HY000] [2002] Operation in progress');

        $this->assertTrue($this->causedByLostConnection($timeout));
        $this->assertTrue(
            $this->causedByLostConnection(new \PDOException('SQLSTATE[HY000] [2002] Operation now in progress'))
        );
        $this->assertFalse($this->causedByLostConnection(new \PDOException('SQLSTATE[42S02] Table not found')));
    }

    public function testProxyAndFailoverMessagesAreLostConnections(): void
    {
        foreach (
            [
                'SQLSTATE[08006] [7] SSL SYSCALL error: EOF detected',
                'SQLSTATE[08006] [7] SSL error: unexpected eof while reading',
                'SQLSTATE[HY000]: General error: 2006 Broken pipe',
                'SQLSTATE[08006] [7] connection to server at "db" (10.0.0.1), port 5432 failed: Connection refused',
                'SQLSTATE[25006]: Read only sql transaction: 7 ERROR:  cannot execute UPDATE',
                'SQLSTATE[HY000]: General error: 1105 vtgate connection error: no healthy endpoints',
                'SQLSTATE[08P01]: <<Unknown error>>: 7 ERROR:  failed to connect to upstream',
            ] as $message
        ) {
            $this->assertTrue($this->causedByLostConnection(new \PDOException($message)), $message);
        }
    }

    public function testCredentialAndFileErrorsAreNotLostConnections(): void
    {
        $this->assertFalse($this->causedByLostConnection(
            new \PDOException("SQLSTATE[HY000] [1045] Access denied for user 'app'@'10.0.0.5' (using password: YES)")
        ));
        $this->assertFalse($this->causedByLostConnection(new \PDOException(
            "SQLSTATE[HY000]: General error: 29 File '/tmp/import.csv' not found (No such file or directory)"
        )));
    }
}
