<?php

namespace MacropaySolutions\Kernel\Tests\Queue;

use MacropaySolutions\Kernel\Bus\UniqueLock;
use MacropaySolutions\Kernel\Cache\ArrayStore;
use MacropaySolutions\Kernel\Cache\Repository;
use MacropaySolutions\Kernel\Container\Container;
use MacropaySolutions\Kernel\Contracts\Cache\Repository as CacheRepository;
use MacropaySolutions\Kernel\Contracts\Debug\ExceptionHandler;
use MacropaySolutions\Kernel\Database\DatabaseTransactionsManager;
use MacropaySolutions\Kernel\Queue\Queue;
use MacropaySolutions\Kernel\Queue\UniqueCallQueuedCallable;
use PHPUnit\Framework\TestCase;

/**
 * A unique job dispatched after commit took its lock at dispatch; when the transaction was rolled back the job was
 * never pushed and the lock stayed until it expired, blocking every new dispatch of that job.
 */
class UniqueAfterCommitRollbackTest extends TestCase
{
    protected function tearDown(): void
    {
        Container::setInstance(null);
    }

    public function testRollbackReleasesTheUniqueLock(): void
    {
        $container = new Container();
        Container::setInstance($container);
        $cache = new Repository(new ArrayStore());
        $container->instance(CacheRepository::class, $cache);
        $transactions = new DatabaseTransactionsManager();
        $container->instance('db.transactions', $transactions);

        $job = UniqueCallQueuedCallable::create([UniqueAfterCommitRollbackTestJob::class, 'handle', ['id' => 1]]);
        $job->afterCommit = true;

        $this->assertTrue((new UniqueLock($cache))->acquire($job));

        $queue = new class extends Queue {
            public function enqueue(object $job): mixed
            {
                return $this->enqueueUsing($job, '{}', 'default', null, static fn(): string => 'id');
            }
        };
        $queue->setContainer($container);

        $transactions->begin('sqlite', 1);
        $queue->enqueue($job);

        $this->assertFalse((new UniqueLock($cache))->acquire($job));

        $transactions->rollback('sqlite', 0);

        $this->assertTrue((new UniqueLock($cache))->acquire($job));
    }

    public function testCacheFailureOnRollbackIsReportedNotThrown(): void
    {
        $container = new Container();
        Container::setInstance($container);
        $container->instance(CacheRepository::class, new class (new ArrayStore()) extends Repository {
            public function fwd(): \MacropaySolutions\Kernel\Contracts\Cache\Store
            {
                throw new \RuntimeException('cache down');
            }
        });
        $handler = new class implements ExceptionHandler {
            public array $reported = [];

            public function report(\Throwable $e)
            {
                $this->reported[] = $e->getMessage();
            }

            public function shouldReport(\Throwable $e)
            {
                return true;
            }

            public function render($request, \Throwable $e)
            {
            }

            public function renderForConsole($output, \Throwable $e)
            {
            }
        };
        $container->instance(ExceptionHandler::class, $handler);
        $transactions = new DatabaseTransactionsManager();
        $container->instance('db.transactions', $transactions);

        $job = UniqueCallQueuedCallable::create([UniqueAfterCommitRollbackTestJob::class, 'handle', ['id' => 1]]);
        $job->afterCommit = true;

        $queue = new class extends Queue {
            public function enqueue(object $job): mixed
            {
                return $this->enqueueUsing($job, '{}', 'default', null, static fn(): string => 'id');
            }
        };
        $queue->setContainer($container);

        $transactions->begin('sqlite', 1);
        $queue->enqueue($job);
        $transactions->rollback('sqlite', 0);

        $this->assertSame(['cache down'], $handler->reported);
    }
}

class UniqueAfterCommitRollbackTestJob
{
    public function handle(int $id): void
    {
    }
}
