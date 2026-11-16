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
        $this->assertFalse($this->causedByLostConnection(new \PDOException('SQLSTATE[42S02] Table not found')));
    }
}
