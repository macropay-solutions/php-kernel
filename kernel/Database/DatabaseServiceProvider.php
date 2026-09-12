<?php

namespace MacropaySolutions\Kernel\Database;

use Faker\Factory as FakerFactory;
use Faker\Generator as FakerGenerator;
use MacropaySolutions\Kernel\Contracts\Queue\EntityResolver;
use MacropaySolutions\Kernel\Contracts\Support\DeferrableProvider;
use MacropaySolutions\Kernel\Database\Connectors\ConnectionFactory;
use MacropaySolutions\Kernel\Database\Obvious\Model;
use MacropaySolutions\Kernel\Database\Obvious\QueueEntityResolver;
use MacropaySolutions\Kernel\Support\ServiceProvider;

class DatabaseServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * The array of resolved Faker instances.
     *
     * @var array
     */
    protected static $fakers = [];

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        Model::clearBootedModels();

        $this->registerConnectionServices();
        $this->registerObviousFactory();
        $this->registerQueueableEntityResolver();
    }

    /**
     * Register the primary database bindings.
     *
     * @return void
     */
    protected function registerConnectionServices()
    {
        // The connection factory is used to create the actual connection instances on
        // the database. We will inject the factory into the manager so that it may
        // make the connections while they are actually needed and not of before.
        $this->app->singleton('db.factory', [self::class, 'getDbFactory']);

        // The database manager is used to resolve various connections, since multiple
        // connections might be managed. It also implements the connection resolver
        // interface which may be used by other components requiring connections.
        $this->app->singleton('db', [self::class, 'getDb']);

        $this->app->bind('db.connection', [self::class, 'getDbConnection']);

        $this->app->bind('db.schema', [self::class, 'getDbSchema']);

        $this->app->singleton('db.transactions', [self::class, 'getDbTransactions']);
    }

    public static function getDb($app)
    {
        return new DatabaseManager($app, $app['db.factory']);
    }

    public static function getDbFactory($app)
    {
        return new ConnectionFactory($app);
    }

    public static function getDbConnection($app)
    {
        return $app['db']->connection();
    }

    public static function getDbSchema($app)
    {
        return $app['db']->connection()->getSchemaBuilder();
    }

    public static function getDbTransactions($app)
    {
        return new DatabaseTransactionsManager();
    }

    /**
     * Register the Obvious factory instance in the container.
     *
     * @return void
     */
    protected function registerObviousFactory()
    {
        $this->app->singleton(FakerGenerator::class, [self::class, 'getFakerGenerator']);
    }

    public static function getFakerGenerator($app, $parameters)
    {
        $locale = $parameters['locale'] ?? $app['config']->get('app.faker_locale', 'en_US');

        if (!isset(static::$fakers[$locale])) {
            static::$fakers[$locale] = FakerFactory::create($locale);
        }

        static::$fakers[$locale]->unique(true);

        return static::$fakers[$locale];
    }

    /**
     * Register the queueable entity resolver implementation.
     *
     * @return void
     */
    protected function registerQueueableEntityResolver()
    {
        $this->app->singleton(EntityResolver::class, [self::class, 'getEntityResolver']);
    }

    public static function getEntityResolver()
    {
        return new QueueEntityResolver();
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            'db.factory',
            'db',
            'db.connection',
            'db.schema',
            'db.transactions',
            FakerGenerator::class,
            EntityResolver::class,
        ];
    }
}
