<?php

namespace MacropaySolutions\Kernel\Queue;

use Aws\DynamoDb\DynamoDbClient;
use MacropaySolutions\Kernel\Contracts\Debug\ExceptionHandler;
use MacropaySolutions\Kernel\Contracts\Support\DeferrableProvider;
use MacropaySolutions\Kernel\Queue\Connectors\BeanstalkdConnector;
use MacropaySolutions\Kernel\Queue\Connectors\DatabaseConnector;
use MacropaySolutions\Kernel\Queue\Connectors\NullConnector;
use MacropaySolutions\Kernel\Queue\Connectors\RedisConnector;
use MacropaySolutions\Kernel\Queue\Connectors\SqsConnector;
use MacropaySolutions\Kernel\Queue\Connectors\SyncConnector;
use MacropaySolutions\Kernel\Queue\Failed\DatabaseFailedJobProvider;
use MacropaySolutions\Kernel\Queue\Failed\DatabaseUuidFailedJobProvider;
use MacropaySolutions\Kernel\Queue\Failed\DynamoDbFailedJobProvider;
use MacropaySolutions\Kernel\Queue\Failed\FileFailedJobProvider;
use MacropaySolutions\Kernel\Queue\Failed\NullFailedJobProvider;
use MacropaySolutions\Kernel\Support\Arr;
use MacropaySolutions\Kernel\Support\ServiceProvider;

class QueueServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('queue', [self::class, 'getQueueManager']);
        $this->app->singleton('queue.connection', [self::class, 'getQueueConnection']);
        $this->app->singleton('queue.worker', [self::class, 'getQueueWorker']);
        $this->app->singleton('queue.listener', [self::class, 'getQueueListener']);
        $this->app->singleton('queue.failer', [self::class, 'getQueueFailer']);
    }

    public static function getQueueManager($app)
    {
        $manager = new QueueManager($app);

        $manager->addConnector('null', static fn() => new NullConnector());
        $manager->addConnector('sync', static fn() => new SyncConnector());
        $manager->addConnector('database', static fn() => new DatabaseConnector($app->make('db')));
        $manager->addConnector('redis', static fn() => new RedisConnector($app->make('redis')));
        $manager->addConnector('beanstalkd', static fn() => new BeanstalkdConnector());
        $manager->addConnector('sqs', static fn() => new SqsConnector());

        return $manager;
    }

    public static function getQueueConnection($app)
    {
        return $app->make('queue')->connection();
    }

    public static function getQueueWorker($app)
    {

        $resetScope = static function () use ($app) {
            $log = $app->make('log');
            $log->flushSharedContext();

            if (\method_exists($log, 'withoutContext')) {
                $log->withoutContext();
            }

            $db = $app->make('db');

            if (\method_exists($db, 'getConnections')) {
                foreach ($db->getConnections() as $connection) {
                    $connection->resetTotalQueryDuration();
                    $connection->allowQueryDurationHandlersToRunAgain();
                }
            }

            $app->forgetScopedInstances();
        };

        return $app->make(Worker::class, [
            $app->make('queue'),
            $app->make('events'),
            $app->make(ExceptionHandler::class),
            $resetScope,
        ]);
    }

    public static function getQueueListener($app)
    {
        return new Listener($app->basePath());
    }

    public static function getQueueFailer($app)
    {
        $config = $app->make('config')->get('queue.failed', []);

        if (
            \array_key_exists('driver', $config) &&
            (\is_null($config['driver']) || $config['driver'] === 'null')
        ) {
            return new NullFailedJobProvider();
        }

        if (isset($config['driver']) && $config['driver'] === 'file') {
            return new FileFailedJobProvider(
                $config['path'] ?? $app->storagePath('framework/cache/failed-jobs.json'),
                $config['limit'] ?? 100,
                static fn() => $app->make('cache')->store('file')
            );
        }

        if (isset($config['driver']) && $config['driver'] === 'dynamodb') {
            $dynamoConfig = [
                'region' => $config['region'],
                'version' => 'latest',
                'endpoint' => $config['endpoint'] ?? null,
            ];

            if (!empty($config['key']) && !empty($config['secret'])) {
                $dynamoConfig['credentials'] = Arr::only(
                    $config,
                    ['key', 'secret', 'token']
                );
            }

            return new DynamoDbFailedJobProvider(
                new DynamoDbClient($dynamoConfig),
                $app->make('config')->get('app.name'),
                $config['table']
            );
        }

        if (isset($config['driver']) && $config['driver'] === 'database-uuids') {
            return new DatabaseUuidFailedJobProvider(
                $app->make('db'),
                $config['database'],
                $config['table']
            );
        }

        if (isset($config['table'])) {
            return new DatabaseFailedJobProvider(
                $app->make('db'),
                $config['database'],
                $config['table']
            );
        }

        return new NullFailedJobProvider();
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            'queue',
            'queue.connection',
            'queue.failer',
            'queue.listener',
            'queue.worker',
        ];
    }
}
