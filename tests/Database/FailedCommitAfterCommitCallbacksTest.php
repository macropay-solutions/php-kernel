<?php

namespace MacropaySolutions\Kernel\Tests\Database;

use ArrayObject;
use MacropaySolutions\Kernel\Database\DatabaseTransactionsManager;
use MacropaySolutions\Kernel\Database\SQLiteConnection;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

/**
 * When the COMMIT itself fails, the afterCommit callbacks registered in that transaction must be dropped:
 * they used to stay pending and run with the next transaction that committed.
 */
class FailedCommitAfterCommitCallbacksTest extends TestCase
{
    public function testCallbacksOfAFailedCommitNeverRun(): void
    {
        $pdo = new class ('sqlite::memory:') extends PDO {
            public bool $failNextCommit = true;

            public function commit(): bool
            {
                if ($this->failNextCommit) {
                    $this->failNextCommit = false;
                    parent::rollBack();

                    throw new PDOException('commit failed');
                }

                return parent::commit();
            }
        };

        $connection = new SQLiteConnection($pdo, config: ['name' => 'sqlite']);
        $manager = new DatabaseTransactionsManager();
        $connection->setTransactionManager($manager);
        $calls = new ArrayObject();

        try {
            $connection->transaction(function (SQLiteConnection $connection) use ($calls): void {
                $connection->afterCommit($this->recorder($calls, 'failed'));
            });
            $this->fail('The commit should have thrown.');
        } catch (PDOException $e) {
            $this->assertSame('commit failed', $e->getMessage());
        }

        $this->assertSame(0, $connection->transactionLevel());
        $this->assertCount(0, $manager->getPendingTransactions());

        $connection->transaction(function (SQLiteConnection $connection) use ($calls): void {
            $connection->afterCommit($this->recorder($calls, 'committed'));
        });

        $this->assertSame(['committed'], $calls->getArrayCopy());
    }

    private function recorder(ArrayObject $calls, string $name): object
    {
        return new class ($calls, $name) {
            public function __construct(private ArrayObject $calls, private string $name)
            {
            }

            public function __invoke(): void
            {
                $this->calls[] = $this->name;
            }
        };
    }
}
