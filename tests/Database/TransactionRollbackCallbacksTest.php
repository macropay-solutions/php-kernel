<?php

namespace MacropaySolutions\Kernel\Tests\Database;

use ArrayObject;
use MacropaySolutions\Kernel\Database\DatabaseTransactionsManager;
use PHPUnit\Framework\TestCase;

/**
 * Callbacks registered for rollback run only for the transactions a rollback discards.
 */
class TransactionRollbackCallbacksTest extends TestCase
{
    public function testCallbacksRunOnlyForDiscardedTransactions(): void
    {
        $calls = new ArrayObject();
        $manager = new DatabaseTransactionsManager();

        $manager->begin('c', 1);
        $manager->begin('c', 2);
        $manager->addCallbackForRollback(fn() => $calls->append('committed savepoint'));
        $manager->commit('c', 2, 1);

        $manager->begin('c', 2);
        $manager->addCallbackForRollback(fn() => $calls->append('rolled back savepoint'));
        $manager->rollback('c', 1);

        $this->assertSame(['rolled back savepoint'], $calls->getArrayCopy());

        $manager->rollback('c', 0);

        $this->assertSame(['rolled back savepoint', 'committed savepoint'], $calls->getArrayCopy());
    }

    public function testCallbacksDoNotRunOnCommit(): void
    {
        $calls = new ArrayObject();
        $manager = new DatabaseTransactionsManager();

        $manager->begin('c', 1);
        $manager->addCallbackForRollback(fn() => $calls->append('rollback'));
        $manager->commit('c', 1, 0);
        $manager->addCallbackForRollback(fn() => $calls->append('outside a transaction'));

        $this->assertSame([], $calls->getArrayCopy());
    }

    public function testAllCallbacksRunAndTheFirstErrorIsRethrown(): void
    {
        $calls = new ArrayObject();
        $manager = new DatabaseTransactionsManager();

        $manager->begin('c', 1);
        $manager->addCallbackForRollback(fn() => throw new \RuntimeException('first'));
        $manager->begin('c', 2);
        $manager->addCallbackForRollback(fn() => throw new \LogicException('second'));
        $manager->addCallbackForRollback(fn() => $calls->append('ran'));

        try {
            $manager->rollback('c', 0);
            $this->fail('No exception thrown.');
        } catch (\RuntimeException | \LogicException $e) {
            $this->assertSame(['ran'], $calls->getArrayCopy());
            $this->assertSame('first', $e->getMessage());
        }
    }
}
