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
        $this->registerManager();
        $this->registerConnection();
        $this->registerWorker();
        $this->registerListener();
        $this->registerFailedJobServices();
    }

    /**
     * Register the queue manager.
     *
     * @return void
     */
    protected function registerManager()
    {
        $this->app->singleton('queue', function ($app) {
            // Once we have an instance of the queue manager, we will register the various
            // resolvers for the queue connectors. These connectors are responsible for
            // creating the classes that accept queue configs and instantiate queues.
            return tap(new QueueManager($app), function ($manager) {
                $this->registerConnectors($manager);
            });
        });
    }

    /**
     * Register the default queue connection binding.
     *
     * @return void
     */
    protected function registerConnection()
    {
        $this->app->singleton('queue.connection', function ($app) {
            return $app['queue']->connection();
        });
    }

    /**
     * Register the connectors on the queue manager.
     *
     * @param \MacropaySolutions\Kernel\Queue\QueueManager $manager
     * @return void
     */
    public function registerConnectors($manager)
    {
        foreach (['Null', 'Sync', 'Database', 'Redis', 'Beanstalkd', 'Sqs'] as $connector) {
            $this->{"register{$connector}Connector"}($manager);
        }
    }

    /**
     * Register the Null queue connector.
     *
     * @param \MacropaySolutions\Kernel\Queue\QueueManager $manager
     * @return void
     */
    protected function registerNullConnector($manager)
    {
        $manager->addConnector('null', function () {
            return new NullConnector();
        });
    }

    /**
     * Register the Sync queue connector.
     *
     * @param \MacropaySolutions\Kernel\Queue\QueueManager $manager
     * @return void
     */
    protected function registerSyncConnector($manager)
    {
        $manager->addConnector('sync', function () {
            return new SyncConnector();
        });
    }

    /**
     * Register the database queue connector.
     *
     * @param \MacropaySolutions\Kernel\Queue\QueueManager $manager
     * @return void
     */
    protected function registerDatabaseConnector($manager)
    {
        $manager->addConnector('database', function () {
            return new DatabaseConnector($this->app['db']);
        });
    }

    /**
     * Register the Redis queue connector.
     *
     * @param \MacropaySolutions\Kernel\Queue\QueueManager $manager
     * @return void
     */
    protected function registerRedisConnector($manager)
    {
        $manager->addConnector('redis', function () {
            return new RedisConnector($this->app['redis']);
        });
    }

    /**
     * Register the Beanstalkd queue connector.
     *
     * @param \MacropaySolutions\Kernel\Queue\QueueManager $manager
     * @return void
     */
    protected function registerBeanstalkdConnector($manager)
    {
        $manager->addConnector('beanstalkd', function () {
            return new BeanstalkdConnector();
        });
    }

    /**
     * Register the Amazon SQS queue connector.
     *
     * @param \MacropaySolutions\Kernel\Queue\QueueManager $manager
     * @return void
     */
    protected function registerSqsConnector($manager)
    {
        $manager->addConnector('sqs', function () {
            return new SqsConnector();
        });
    }

    /**
     * Register the queue worker.
     *
     * @return void
     */
    protected function registerWorker()
    {
        $this->app->singleton('queue.worker', function ($app) {
            $isDownForMaintenance = function () {
                return $this->app->isDownForMaintenance();
            };

            $resetScope = function () use ($app) {
                $app['log']->flushSharedContext();

                if (method_exists($app['log'], 'withoutContext')) {
                    $app['log']->withoutContext();
                }

                if (method_exists($app['db'], 'getConnections')) {
                    foreach ($app['db']->getConnections() as $connection) {
                        $connection->resetTotalQueryDuration();
                        $connection->allowQueryDurationHandlersToRunAgain();
                    }
                }

                $app->forgetScopedInstances();
            };

            //return new Worker(
            //return \di(Worker::class, [
            return $app->make(Worker::class, [
                $app['queue'],
                $app['events'],
                $app[ExceptionHandler::class],
                $isDownForMaintenance,
                $resetScope
            ]);
        });
    }

    /**
     * Register the queue listener.
     *
     * @return void
     */
    protected function registerListener()
    {
        $this->app->singleton('queue.listener', function ($app) {
            return new Listener($app->basePath());
        });
    }

    /**
     * Register the failed job services.
     *
     * @return void
     */
    protected function registerFailedJobServices()
    {
        $this->app->singleton('queue.failer', function ($app) {
            $config = $app['config']['queue.failed'];

            if (
                array_key_exists('driver', $config) &&
                (is_null($config['driver']) || $config['driver'] === 'null')
            ) {
                return new NullFailedJobProvider();
            }

            if (isset($config['driver']) && $config['driver'] === 'file') {
                return new FileFailedJobProvider(
                    $config['path'] ?? $this->app->storagePath('framework/cache/failed-jobs.json'),
                    $config['limit'] ?? 100,
                    fn() => $app['cache']->store('file'),
                );
            } elseif (isset($config['driver']) && $config['driver'] === 'dynamodb') {
                return $this->dynamoFailedJobProvider($config);
            } elseif (isset($config['driver']) && $config['driver'] === 'database-uuids') {
                return $this->databaseUuidFailedJobProvider($config);
            } elseif (isset($config['table'])) {
                return $this->databaseFailedJobProvider($config);
            } else {
                return new NullFailedJobProvider();
            }
        });
    }

    /**
     * Create a new database failed job provider.
     *
     * @param array $config
     * @return \MacropaySolutions\Kernel\Queue\Failed\DatabaseFailedJobProvider
     */
    protected function databaseFailedJobProvider($config)
    {
        return new DatabaseFailedJobProvider(
            $this->app['db'],
            $config['database'],
            $config['table']
        );
    }

    /**
     * Create a new database failed job provider that uses UUIDs as IDs.
     *
     * @param array $config
     * @return \MacropaySolutions\Kernel\Queue\Failed\DatabaseUuidFailedJobProvider
     */
    protected function databaseUuidFailedJobProvider($config)
    {
        return new DatabaseUuidFailedJobProvider(
            $this->app['db'],
            $config['database'],
            $config['table']
        );
    }

    /**
     * Create a new DynamoDb failed job provider.
     *
     * @param array $config
     * @return \MacropaySolutions\Kernel\Queue\Failed\DynamoDbFailedJobProvider
     */
    protected function dynamoFailedJobProvider($config)
    {
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
            $this->app['config']['app.name'],
            $config['table']
        );
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
