<?php

namespace MacropaySolutions\Framework;

use MacropaySolutions\Kernel\Auth\Access\Gate;
use MacropaySolutions\Kernel\Auth\AuthServiceProvider;
use MacropaySolutions\Kernel\Broadcasting\BroadcastServiceProvider;
use MacropaySolutions\Kernel\Bus\BusServiceProvider;
use MacropaySolutions\Kernel\Cache\CacheServiceProvider;
use MacropaySolutions\Kernel\Config\Repository as ConfigRepository;
use MacropaySolutions\Kernel\Container\Container;
use MacropaySolutions\Kernel\Contracts\Container\BindingResolutionException;
use MacropaySolutions\Kernel\Contracts\Foundation\Application as ApplicationContract;
use MacropaySolutions\Kernel\Contracts\Foundation\MaintenanceMode as MaintenanceModeContract;
use MacropaySolutions\Kernel\Cookie\CookieServiceProvider;
use MacropaySolutions\Kernel\Database\DatabaseServiceProvider;
use MacropaySolutions\Kernel\Database\MigrationServiceProvider;
use MacropaySolutions\Kernel\Encryption\EncryptionServiceProvider;
use MacropaySolutions\Kernel\Events\EventServiceProvider;
use MacropaySolutions\Kernel\Filesystem\Filesystem;
use MacropaySolutions\Kernel\Filesystem\FilesystemServiceProvider;
use MacropaySolutions\Kernel\Hashing\HashServiceProvider;
use MacropaySolutions\Kernel\Log\LogManager;
use MacropaySolutions\Kernel\Pagination\PaginationServiceProvider;
use MacropaySolutions\Kernel\Queue\QueueServiceProvider;
use MacropaySolutions\Kernel\Session\SessionServiceProvider;
use MacropaySolutions\Kernel\Support\Arr;
use MacropaySolutions\Kernel\Support\Composer;
use MacropaySolutions\Kernel\Support\ServiceProvider;
use MacropaySolutions\Kernel\Support\Str;
use MacropaySolutions\Kernel\Translation\TranslationServiceProvider;
use MacropaySolutions\Kernel\Validation\ValidationServiceProvider;
use MacropaySolutions\Kernel\View\ViewServiceProvider;
use MacropaySolutions\Framework\Console\ConsoleServiceProvider;
use MacropaySolutions\Framework\Routing\Router;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response as PsrResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;

use function MacropaySolutions\Kernel\Filesystem\join_paths;

class Application extends Container implements ApplicationContract
{
    use Concerns\RoutesRequests;
    use Concerns\RegistersExceptionHandlers;

    /**
     * Indicates if the class aliases have been registered.
     *
     * @var bool
     */
    protected static $aliasesRegistered = false;

    /**
     * The base path of the application installation.
     *
     * @var string
     */
    protected $basePath;

    /**
     * All the loaded configuration files.
     *
     * @var array
     */
    protected $loadedConfigurations = [];

    /**
     * Indicates if the application has been bootstrapped before.
     */
    protected bool $hasBeenBootstrapped = false;

    /**
     * Indicates if the application has "booted".
     *
     * @var bool
     */
    protected $booted = false;

    /**
     * The loaded service providers.
     *
     * @var array
     */
    protected $loadedProviders = [];

    /**
     * The service binding methods that have been executed.
     *
     * @var array
     */
    protected $ranServiceBinders = [];

    /**
     * The custom storage path defined by the developer.
     *
     * @var string
     */
    protected $storagePath;

    /**
     * The application namespace.
     *
     * @var string
     */
    protected $namespace;

    /**
     * The Router instance.
     *
     * @var \MacropaySolutions\Framework\Routing\Router
     */
    public $router;

    /**
     * The array of terminating callbacks.
     *
     * @var callable[]
     */
    protected $terminatingCallbacks = [];

    /**
     * Create a new Framework application instance.
     *
     * @param string|null $basePath
     * @return void
     */
    public function __construct($basePath = null)
    {
        $this->basePath = $basePath;

        static::$bootstrapCachedFiles ??= static::getBootstrapCachedFiles($this->bootstrapPath('cache'));
        static::$isDevEnv = \class_exists(\MacropaySolutions\KernelDev\ServiceProvider::class);

        $this->bootstrapContainer();
        $this->registerErrorHandling();
        $this->bootstrapRouter();
    }

    /**
     * Bootstrap the application container.
     *
     * @return void
     */
    protected function bootstrapContainer()
    {
        static::setInstance($this);

        $this->registerExplicitBindingsMap();

        $this->instance('app', $this);
        $this->instance(self::class, $this);

        $this->instance('path', $this->path());

        $this->instance('env', $this->environment());

        $this->registerContainerAliases();
    }

    /**
     * Bootstrap the router instance.
     *
     * @return void
     */
    public function bootstrapRouter()
    {
        $this->router = new Router($this);
    }

    /**
     * Determine if the application is currently down for maintenance.
     *
     * @return bool
     */
    public function isDownForMaintenance()
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function environment(...$environments): mixed
    {
        $env = $this->cachedEnvironment ??= (\env('APP_ENV') ?? \config('app.env', 'production'));

        if ($environments === []) {
            return $env;
        }

        return Str::is(\is_array($first = \reset($environments)) ? $first : $environments, $env);
    }

    /**
     * Determine if the application is in the local environment.
     *
     * @return bool
     */
    public function isLocal()
    {
        return $this->environment() === 'local';
    }

    /**
     * Determine if the application is in the production environment.
     *
     * @return bool
     */
    public function isProduction()
    {
        return $this->environment() === 'production';
    }

    /**
     * Determine if the given service provider is loaded.
     *
     * @param string $provider
     * @return bool
     */
    public function providerIsLoaded(string $provider)
    {
        return isset($this->loadedProviders[$provider]);
    }

    /**
     * @inheritdoc
     */
    public function register($provider, $force = false): void
    {
        if (
            \array_key_exists(
                $providerName = \is_object($provider) ? $provider::class : \ltrim($provider, '\\'),
                $this->loadedProviders
            )
        ) {
            return;
        }

        if (!$provider instanceof ServiceProvider) {
            $provider = new $provider($this);
        }

        $this->loadedProviders[$providerName] = $provider;

        $provider->register();

        if ($this->booted) {
            $this->bootProvider($provider);
        }
    }

    /**
     * @inheritdoc
     */
    public function registerDeferredProvider($provider, $service = null): void
    {
        $this->register($provider);
    }

    public function isBooted(): bool
    {
        return $this->booted;
    }

    /**
     * Boots the registered providers.
     */
    public function boot()
    {
        if ($this->booted) {
            return;
        }

        static::$circularDependencyMemoryLimit =
            (int)$this->make('config')->get('app.circular_dependency_memory_limit', 0);

        foreach ($this->loadedProviders as $provider) {
            $this->bootProvider($provider);
        }

        $this->booted = true;
    }

    /**
     * Boot the given service provider.
     *
     * @param \MacropaySolutions\Kernel\Support\ServiceProvider $provider
     * @return mixed
     */
    protected function bootProvider(ServiceProvider $provider)
    {
        if (\method_exists($provider, 'boot')) {
            return $this->call([$provider, 'boot']);
        }
    }

    /**
     * Resolve the given type from the container.
     *
     * @param string $abstract
     * @param array $parameters
     * @return mixed
     */
    public function make($abstract, array $parameters = [])
    {
        return parent::make($this->handleDeferredProvidersAndReturnAlias($abstract), $parameters);
    }

    /**
     * @inheritdoc
     */
    public function makeWithoutAlias(string $abstract, array $parameters = []): mixed
    {
        $this->handleDeferredProvidersAndReturnAlias($abstract);

        return parent::makeWithoutAlias($abstract, $parameters);
    }

    protected function handleDeferredProvidersAndReturnAlias(string $abstract): string
    {
        $alias = $this->getAlias($abstract);

        if (
            !isset($this->bindings[$alias]) &&
            !isset($this->instances[$alias]) &&
            isset($this->availableBindings[$alias]) &&
            !isset($this->ranServiceBinders[$this->availableBindings[$alias]])
        ) {
            $this->{$this->availableBindings[$alias]}();

            $this->ranServiceBinders[$this->availableBindings[$alias]] = true;
        }

        return $alias;
    }

    /**
     * @inheritdoc
     */
    public function has(string $id): bool
    {
        return isset($this->availableBindings[$id]) ||
            isset($this->bindings[$id]) ||
            isset($this->instances[$id]) ||
            (
                isset($this->aliases[$id]) && (
                    isset($this->availableBindings[$alias = $this->getAlias($id)]) ||
                    isset($this->bindings[$alias]) ||
                    isset($this->instances[$alias])
                )
            );
    }

    /**
     * Determine if a given offset exists.
     *
     * @param string $offset
     */
    public function offsetExists($offset): bool
    {
        return $this->has($offset);
    }

    /**
     * Register container bindings for the application.
     *
     * @return void
     */
    protected function registerAuthBindings()
    {
        $this->configure('auth');
        $this->register(AuthServiceProvider::class);
    }

    /**
     * Register container bindings for the application.
     *
     * @return void
     */
    protected function registerGateAuthBindings()
    {
        if (!isset($this->ranServiceBinders['registerAuthBindings'])) {
            $this->registerAuthBindings();
            $this->ranServiceBinders['registerAuthBindings'] = true;
        }

        $this->singleton(\MacropaySolutions\Kernel\Contracts\Auth\Access\Gate::class, function ($app) {
            return new Gate($app, fn() => call_user_func($app['auth']->userResolver()));
        });
    }

    /**
     * Register container bindings for the application.
     *
     * @return void
     */
    protected function registerBroadcastingBindings()
    {
        $this->configure('broadcasting');
        $this->register(BroadcastServiceProvider::class);
    }

    /**
     * Register container bindings for the application.
     *
     * @return void
     */
    protected function registerBusBindings()
    {
        $this->register(BusServiceProvider::class);
    }

    /**
     * Register container bindings for the application.
     *
     * @return void
     */
    protected function registerCacheBindings()
    {
        $this->configure('cache');
        $this->register(CacheServiceProvider::class);
    }

    /**
     * Register container bindings for the application.
     *
     * @return void
     */
    protected function registerComposerBindings()
    {
        $this->singleton('composer', function ($app) {
            return new Composer($app->make('files'), $this->basePath());
        });
    }

    /**
     * Register container bindings for the application.
     *
     * @return void
     */
    protected function registerConfigBindings()
    {
        $this->singleton('config', function () {
            return new ConfigRepository();
        });
    }

    /**
     * Register container bindings for the application.
     *
     * @return void
     */
    protected function registerDatabaseBindings()
    {
        $this->configure('app');
        $this->configure('database');

        $this->register(DatabaseServiceProvider::class);
        $this->register(PaginationServiceProvider::class);
    }

    /**
     * Register container bindings for the application.
     *
     * @return void
     */
    protected function registerEncrypterBindings()
    {
        $this->configure('app');
        $this->register(EncryptionServiceProvider::class);
    }

    /**
     * Register container bindings for the application.
     *
     * @return void
     */
    protected function registerEventBindings()
    {
        $this->register(EventServiceProvider::class);
    }

    /**
     * Register container bindings for the application.
     *
     * @return void
     */
    protected function registerFilesBindings()
    {
        $this->singleton('files', function () {
            return new Filesystem();
        });
    }

    /**
     * Register container bindings for the application.
     *
     * @return void
     */
    protected function registerFilesystemBindings()
    {
        $this->configure('filesystem');
        $this->register(FilesystemServiceProvider::class);
    }

    /**
     * Register container bindings for the application.
     *
     * @return void
     */
    protected function registerHashBindings()
    {
        $this->register(HashServiceProvider::class);
    }

    /**
     * Register container bindings for the application.
     *
     * @return void
     */
    protected function registerLogBindings()
    {
        $this->singleton(LoggerInterface::class, function () {
            $this->configure('logging');

            return new LogManager($this);
        });
    }

    /**
     * Register container bindings for the application.
     *
     * @return void
     */
    protected function registerQueueBindings()
    {
        $this->configure('queue');
        $this->register(QueueServiceProvider::class);
    }

    /**
     * Register container bindings for the application.
     *
     * @return void
     */
    protected function registerRouterBindings()
    {
        $this->singleton('router', function () {
            return $this->router;
        });
    }

    /**
     * Register container bindings for the PSR-7 request implementation.
     *
     * @return void
     */
    protected function registerPsrRequestBindings()
    {
        $this->singleton(ServerRequestInterface::class, function ($app) {
            if (class_exists(Psr17Factory::class) && class_exists(PsrHttpFactory::class)) {
                $psr17Factory = new Psr17Factory();

                return (new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory))
                    ->createRequest($app->make('request'));
            }

            throw new BindingResolutionException(
                'Unable to resolve PSR request. Please install symfony/psr-http-message-bridge and nyholm/psr7.'
            );
        });
    }

    /**
     * Register container bindings for the PSR-7 response implementation.
     *
     * @return void
     */
    protected function registerPsrResponseBindings()
    {
        $this->singleton(ResponseInterface::class, function () {
            if (class_exists(PsrResponse::class)) {
                return new PsrResponse();
            }

            throw new BindingResolutionException('Unable to resolve PSR response. Please install nyholm/psr7.');
        });
    }

    /**
     * Register container bindings for the application.
     *
     * @return void
     */
    protected function registerTranslationBindings()
    {
        $this->configure('app');
        $this->instance('path.lang', $this->getLanguagePath());
        $this->register(TranslationServiceProvider::class);
    }

    /**
     * Get the path to the application's language files.
     *
     * @return string
     */
    protected function getLanguagePath()
    {
        if (is_dir($langPath = $this->basePath() . '/resources/lang')) {
            return $langPath;
        } else {
            return __DIR__ . '/../resources/lang';
        }
    }

    /**
     * Register container bindings for the application.
     *
     * @return void
     */
    protected function registerUrlGeneratorBindings()
    {
        $this->singleton('url', function () {
            return new Routing\UrlGenerator($this);
        });
    }

    /**
     * Register container bindings for the application.
     *
     * @return void
     */
    protected function registerValidatorBindings()
    {
        $this->register(ValidationServiceProvider::class);
    }

    /**
     * Register container bindings for the application.
     *
     * @return void
     */
    protected function registerViewBindings()
    {
        $this->configure('view');
        $this->register(ViewServiceProvider::class);
    }

    /**
     * Register container bindings for the application.
     *
     * @return void
     */
    protected function registerSessionBindings()
    {
        $this->configure('session');
        $this->register(SessionServiceProvider::class);
    }

    /**
     * Register container bindings for the application.
     *
     * @return void
     */
    protected function registerCookieBindings()
    {
        $this->configure('session');
        $this->register(CookieServiceProvider::class);
    }

    /**
     * Configure and load the given component and provider.
     *
     * @param string $config
     * @param array|string $providers
     * @param string|null $return
     * @return mixed
     */
    public function loadComponent($config, $providers, $return = null)
    {
        $this->configure($config);

        foreach ((array)$providers as $provider) {
            $this->register($provider);
        }

        return $this->make($return ?: $config);
    }

    /**
     * Load a configuration file into the application.
     *
     * @param string $name
     * @return void
     */
    public function configure($name): void
    {
        if (isset($this->loadedConfigurations[$name])) {
            return;
        }

        $this->loadedConfigurations[$name] = true;

        if ($this->configurationIsCached()) {
            return;
        }

        if ('' !== (string)($path = $this->getConfigurationPath($name))) {
            $this->make('config')->set($name, require $path);
        }
    }

    /**
     * Get the path to the given configuration file.
     *
     * If no name is provided, then we'll return the path to the config folder.
     *
     * @param string|null $name
     * @return string|null
     */
    public function getConfigurationPath($name = null)
    {
        if (!$name) {
            $appConfigDir = $this->basePath('config') . '/';

            if (file_exists($appConfigDir)) {
                return $appConfigDir;
            }

            if (file_exists($path = __DIR__ . '/../config/')) {
                return $path;
            }

            return null;
        }

        $appConfigPath = $this->basePath('config') . '/' . $name . '.php';

        if (file_exists($appConfigPath)) {
            return $appConfigPath;
        }

        if (file_exists($path = __DIR__ . '/../config/' . $name . '.php')) {
            return $path;
        }

        return null;
    }

    /**
     * Register the aliases for the application.
     *
     * @param array $userAliases
     * @return void
     */
    public function withAliases($userAliases = [])
    {
        $defaults = [];

        if (!static::$aliasesRegistered) {
            static::$aliasesRegistered = true;

            $merged = array_merge($defaults, $userAliases);

            foreach ($merged as $original => $alias) {
                if (!class_exists($alias)) {
                    class_alias($original, $alias);
                }
            }
        }
    }

    /**
     * Load the Obvious library for the application.
     *
     * @return void
     */
    public function withObvious()
    {
        $this->make('db');
    }

    /**
     * Get the path to the application "app" directory.
     *
     * @return string
     */
    public function path()
    {
        return $this->basePath . DIRECTORY_SEPARATOR . 'app';
    }

    /**
     * Get the base path for the application.
     *
     * @param string $path
     * @return string
     */
    public function basePath($path = '')
    {
        if (isset($this->basePath)) {
            return $this->basePath . ($path ? '/' . $path : $path);
        }

        if ($this->runningInConsole()) {
            $this->basePath = getcwd();
        } else {
            $this->basePath = realpath(getcwd() . '/../');
        }

        return $this->basePath($path);
    }

    /**
     * Get the path to the application configuration files.
     *
     * @param string $path
     * @return string
     */
    public function configPath($path = '')
    {
        return $this->basePath . DIRECTORY_SEPARATOR . 'config' . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }

    /**
     * Get the path to the database directory.
     *
     * @param string $path
     * @return string
     */
    public function databasePath($path = '')
    {
        return $this->basePath . DIRECTORY_SEPARATOR . 'database' . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }

    /**
     * Get the path to the language files.
     *
     * @param string $path
     * @return string
     */
    public function langPath($path = '')
    {
        return $this->getLanguagePath() . ($path != '' ? DIRECTORY_SEPARATOR . $path : '');
    }

    /**
     * Get the storage path for the application.
     *
     * @param string|null $path
     * @return string
     */
    public function storagePath($path = '')
    {
        return ($this->storagePath ?: $this->basePath . DIRECTORY_SEPARATOR . 'storage') .
            ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }

    /**
     * Set the storage directory.
     *
     * @param string $path
     * @return $this
     */
    public function useStoragePath($path)
    {
        $this->storagePath = $path;

        $this->instance('path.storage', $path);

        return $this;
    }

    /**
     * Get the path to the resources directory.
     *
     * @param string|null $path
     * @return string
     */
    public function resourcePath($path = '')
    {
        return $this->basePath . DIRECTORY_SEPARATOR . 'resources' . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }

    /**
     * Determine if the application is running in the console.
     *
     * @return bool
     */
    public function runningInConsole()
    {
        return \PHP_SAPI === 'cli' || \PHP_SAPI === 'phpdbg';
    }

    /**
     * Determine if we are running unit tests.
     *
     * @return bool
     */
    public function runningUnitTests()
    {
        return $this->environment() === 'testing';
    }

    /**
     * Prepare the application to execute a console command.
     *
     * @param bool $aliases
     * @return void
     */
    public function prepareForConsoleCommand($aliases = true)
    {
        $this->make('cache');
        $this->make('queue');

        $this->configure('database');

        $this->register(MigrationServiceProvider::class);
        $this->register(ConsoleServiceProvider::class);

        if (static::$isDevEnv) {
            $this->register(\MacropaySolutions\KernelDev\ServiceProvider::class);
        }
    }

    /**
     * Get the application namespace.
     *
     * @return string
     *
     * @throws \RuntimeException
     */
    public function getNamespace()
    {
        if (!is_null($this->namespace)) {
            return $this->namespace;
        }

        $composer = json_decode(file_get_contents(base_path('composer.json')), true);

        foreach ((array)data_get($composer, 'autoload.psr-4') as $namespace => $path) {
            foreach ((array)$path as $pathChoice) {
                if (realpath(app()->path()) == realpath(base_path() . '/' . $pathChoice)) {
                    return $this->namespace = $namespace;
                }
            }
        }

        throw new RuntimeException('Unable to detect application namespace.');
    }

    /**
     * Flush the container of all bindings and resolved instances.
     *
     * @return void
     */
    public function flush()
    {
        parent::flush();

        $this->middleware = [];
        $this->currentRoute = [];
        $this->loadedProviders = [];
        $this->routeMiddleware = [];
        $this->reboundCallbacks = [];
        $this->resolvingCallbacks = [];
        $this->availableBindings = [];
        $this->ranServiceBinders = [];
        $this->loadedConfigurations = [];
        $this->afterResolvingCallbacks = [];

        $this->router = null;
        $this->dispatcher = null;
        static::$instance = null;
        static::$aliasesRegistered = false;
    }

    /**
     * Get the current application locale.
     *
     * @return string
     */
    public function getLocale()
    {
        return $this->make('config')->get('app.locale');
    }

    /**
     * Get the current application fallback locale.
     *
     * @return string
     */
    public function getFallbackLocale()
    {
        return $this->make('config')->get('app.fallback_locale');
    }

    /**
     * Set the current application locale.
     *
     * @param string $locale
     * @return void
     */
    public function setLocale($locale)
    {
        $this->make('config')->set('app.locale', $locale);
        $this->make('translator')->setLocale($locale);
    }

    /**
     * Determine if application locale is the given locale.
     *
     * @param string $locale
     * @return bool
     */
    public function isLocale($locale)
    {
        return $this->getLocale() == $locale;
    }

    /**
     * Register a terminating callback with the application.
     *
     * @param callable|string $callback
     * @return $this
     */
    public function terminating($callback)
    {
        $this->terminatingCallbacks[] = $callback;

        return $this;
    }

    /**
     * Terminate the application.
     *
     * @return void
     */
    public function terminate()
    {
        $index = 0;

        while ($index < count($this->terminatingCallbacks)) {
            $this->call($this->terminatingCallbacks[$index]);

            $index++;
        }
    }

    /**
     * Register the core container aliases.
     *
     * @return void
     */
    protected function registerContainerAliases()
    {
        $this->abstractAliases = [
            'app' => [
                \MacropaySolutions\Kernel\Contracts\Foundation\Application::class,
                \MacropaySolutions\Kernel\Container\Container::class,
                \MacropaySolutions\Kernel\Contracts\Container\Container::class,
            ],
            'auth' => [
                \MacropaySolutions\Kernel\Contracts\Auth\Factory::class,
                \MacropaySolutions\Kernel\Auth\AuthManager::class,
            ],
            'auth.driver' => [
                \MacropaySolutions\Kernel\Contracts\Auth\Guard::class,
            ],
            'cache' => [
                \MacropaySolutions\Kernel\Contracts\Cache\Factory::class,
                \MacropaySolutions\Kernel\Cache\CacheManager::class,
            ],
            'cache.store' => [
                \MacropaySolutions\Kernel\Contracts\Cache\Repository::class,
            ],
            'config' => [
                \MacropaySolutions\Kernel\Contracts\Config\Repository::class,
                \MacropaySolutions\Kernel\Config\Repository::class,
            ],
            'db' => [
                \MacropaySolutions\Kernel\Database\ConnectionResolverInterface::class,
                \MacropaySolutions\Kernel\Database\DatabaseManager::class,
            ],
            'encrypter' => [
                \MacropaySolutions\Kernel\Contracts\Encryption\Encrypter::class,
                \MacropaySolutions\Kernel\Encryption\Encrypter::class,
            ],
            'events' => [
                \MacropaySolutions\Kernel\Contracts\Events\Dispatcher::class,
                \MacropaySolutions\Kernel\Events\Dispatcher::class,
            ],
            'filesystem' => [
                \MacropaySolutions\Kernel\Contracts\Filesystem\Factory::class,
                \MacropaySolutions\Kernel\Filesystem\FilesystemManager::class,
            ],
            'filesystem.disk' => [
                \MacropaySolutions\Kernel\Contracts\Filesystem\Filesystem::class,
            ],
            'filesystem.cloud' => [
                \MacropaySolutions\Kernel\Contracts\Filesystem\Cloud::class,
            ],
            'hash' => [
                \MacropaySolutions\Kernel\Contracts\Hashing\Hasher::class,
                \MacropaySolutions\Kernel\Hashing\HashManager::class,
            ],
            // quirk: Key is the Interface for 'log'
            \Psr\Log\LoggerInterface::class => [
                'log',
            ],
            'queue' => [
                \MacropaySolutions\Kernel\Contracts\Queue\Factory::class,
                \MacropaySolutions\Kernel\Queue\QueueManager::class,
            ],
            'queue.connection' => [
                \MacropaySolutions\Kernel\Contracts\Queue\Queue::class,
            ],
            'redis' => [
                \MacropaySolutions\Kernel\Contracts\Redis\Factory::class,
                \MacropaySolutions\Kernel\Redis\RedisManager::class,
            ],
            'redis.connection' => [
                \MacropaySolutions\Kernel\Redis\Connections\Connection::class,
                \MacropaySolutions\Kernel\Contracts\Redis\Connection::class,
            ],
            // quirk: Key is the Request class
            \MacropaySolutions\Kernel\Http\Request::class => [
                'request',
            ],
            'router' => [
                \MacropaySolutions\Framework\Routing\Router::class,
            ],
            'translator' => [
                \MacropaySolutions\Kernel\Contracts\Translation\Translator::class,
                \MacropaySolutions\Kernel\Translation\Translator::class,
            ],
            'url' => [
                \MacropaySolutions\Framework\Routing\UrlGenerator::class,
            ],
            'validator' => [
                \MacropaySolutions\Kernel\Contracts\Validation\Factory::class,
                \MacropaySolutions\Kernel\Validation\Factory::class,
            ],
            'view' => [
                \MacropaySolutions\Kernel\Contracts\View\Factory::class,
                \MacropaySolutions\Kernel\View\Factory::class,
            ],
            'session' => [
                \MacropaySolutions\Kernel\Session\SessionManager::class,
            ],
            'session.store' => [
                \MacropaySolutions\Kernel\Session\Store::class,
                \MacropaySolutions\Kernel\Contracts\Session\Session::class,
            ],
            'cookie' => [
                \MacropaySolutions\Kernel\Cookie\CookieJar::class,
                \MacropaySolutions\Kernel\Contracts\Cookie\Factory::class,
                \MacropaySolutions\Kernel\Contracts\Cookie\QueueingFactory::class,
            ],
            'files' => [
                \MacropaySolutions\Kernel\Filesystem\Filesystem::class,
            ],
            'template.compiler' => [
                \MacropaySolutions\Kernel\View\Compilers\TemplateCompiler::class,
            ],
            'view.engine.resolver' => [
                \MacropaySolutions\Kernel\View\Engines\EngineResolver::class,
            ],
        ];
        $this->aliases = [
            \MacropaySolutions\Kernel\Contracts\Foundation\Application::class => 'app',
            \MacropaySolutions\Kernel\Contracts\Auth\Factory::class => 'auth',
            \MacropaySolutions\Kernel\Contracts\Auth\Guard::class => 'auth.driver',
            \MacropaySolutions\Kernel\Contracts\Cache\Factory::class => 'cache',
            \MacropaySolutions\Kernel\Contracts\Cache\Repository::class => 'cache.store',
            \MacropaySolutions\Kernel\Contracts\Config\Repository::class => 'config',
            \MacropaySolutions\Kernel\Config\Repository::class => 'config',
            \MacropaySolutions\Kernel\Container\Container::class => 'app',
            \MacropaySolutions\Kernel\Contracts\Container\Container::class => 'app',
            \MacropaySolutions\Kernel\Database\ConnectionResolverInterface::class => 'db',
            \MacropaySolutions\Kernel\Database\DatabaseManager::class => 'db',
            \MacropaySolutions\Kernel\Contracts\Encryption\Encrypter::class => 'encrypter',
            \MacropaySolutions\Kernel\Contracts\Events\Dispatcher::class => 'events',
            \MacropaySolutions\Kernel\Contracts\Filesystem\Factory::class => 'filesystem',
            \MacropaySolutions\Kernel\Contracts\Filesystem\Filesystem::class => 'filesystem.disk',
            \MacropaySolutions\Kernel\Contracts\Filesystem\Cloud::class => 'filesystem.cloud',
            \MacropaySolutions\Kernel\Contracts\Hashing\Hasher::class => 'hash',
            'log' => \Psr\Log\LoggerInterface::class,
            \MacropaySolutions\Kernel\Contracts\Queue\Factory::class => 'queue',
            \MacropaySolutions\Kernel\Contracts\Queue\Queue::class => 'queue.connection',
            \MacropaySolutions\Kernel\Redis\RedisManager::class => 'redis',
            \MacropaySolutions\Kernel\Contracts\Redis\Factory::class => 'redis',
            \MacropaySolutions\Kernel\Redis\Connections\Connection::class => 'redis.connection',
            \MacropaySolutions\Kernel\Contracts\Redis\Connection::class => 'redis.connection',
            'request' => \MacropaySolutions\Kernel\Http\Request::class,
            \MacropaySolutions\Framework\Routing\Router::class => 'router',
            \MacropaySolutions\Kernel\Contracts\Translation\Translator::class => 'translator',
            \MacropaySolutions\Framework\Routing\UrlGenerator::class => 'url',
            \MacropaySolutions\Kernel\Contracts\Validation\Factory::class => 'validator',
            \MacropaySolutions\Kernel\Contracts\View\Factory::class => 'view',
            \MacropaySolutions\Kernel\Session\SessionManager::class => 'session',
            \MacropaySolutions\Kernel\Session\Store::class => 'session.store',
            \MacropaySolutions\Kernel\Contracts\Session\Session::class => 'session.store',
            \MacropaySolutions\Kernel\Cookie\CookieJar::class => 'cookie',
            \MacropaySolutions\Kernel\Contracts\Cookie\Factory::class => 'cookie',
            \MacropaySolutions\Kernel\Contracts\Cookie\QueueingFactory::class => 'cookie',
            \MacropaySolutions\Kernel\Auth\AuthManager::class => 'auth',
            \MacropaySolutions\Kernel\Cache\CacheManager::class => 'cache',
            \MacropaySolutions\Kernel\Encryption\Encrypter::class => 'encrypter',
            \MacropaySolutions\Kernel\Events\Dispatcher::class => 'events',
            \MacropaySolutions\Kernel\Filesystem\FilesystemManager::class => 'filesystem',
            \MacropaySolutions\Kernel\Filesystem\Filesystem::class => 'files',
            \MacropaySolutions\Kernel\Hashing\HashManager::class => 'hash',
            \MacropaySolutions\Kernel\Queue\QueueManager::class => 'queue',
            \MacropaySolutions\Kernel\Translation\Translator::class => 'translator',
            \MacropaySolutions\Kernel\Validation\Factory::class => 'validator',
            \MacropaySolutions\Kernel\View\Factory::class => 'view',
            \MacropaySolutions\Kernel\View\Compilers\TemplateCompiler::class => 'template.compiler',
            \MacropaySolutions\Kernel\View\Engines\EngineResolver::class => 'view.engine.resolver',
        ];
    }

    /**
     * The available container bindings and their respective load methods.
     *
     * @var array
     */
    public $availableBindings = [
        'auth' => 'registerAuthBindings',
        'auth.driver' => 'registerAuthBindings',
        \MacropaySolutions\Kernel\Auth\AuthManager::class => 'registerAuthBindings',
        \MacropaySolutions\Kernel\Contracts\Auth\Guard::class => 'registerAuthBindings',
        \MacropaySolutions\Kernel\Contracts\Auth\Access\Gate::class => 'registerGateAuthBindings',
        \MacropaySolutions\Kernel\Contracts\Broadcasting\Broadcaster::class => 'registerBroadcastingBindings',
        \MacropaySolutions\Kernel\Contracts\Broadcasting\Factory::class => 'registerBroadcastingBindings',
        \MacropaySolutions\Kernel\Contracts\Bus\Dispatcher::class => 'registerBusBindings',
        'cache' => 'registerCacheBindings',
        'cache.store' => 'registerCacheBindings',
        \MacropaySolutions\Kernel\Contracts\Cache\Factory::class => 'registerCacheBindings',
        \MacropaySolutions\Kernel\Contracts\Cache\Repository::class => 'registerCacheBindings',
        'composer' => 'registerComposerBindings',
        'config' => 'registerConfigBindings',
        'db' => 'registerDatabaseBindings',
        'filesystem' => 'registerFilesystemBindings',
        'filesystem.cloud' => 'registerFilesystemBindings',
        'filesystem.disk' => 'registerFilesystemBindings',
        \MacropaySolutions\Kernel\Contracts\Filesystem\Cloud::class => 'registerFilesystemBindings',
        \MacropaySolutions\Kernel\Contracts\Filesystem\Filesystem::class => 'registerFilesystemBindings',
        \MacropaySolutions\Kernel\Contracts\Filesystem\Factory::class => 'registerFilesystemBindings',
        'encrypter' => 'registerEncrypterBindings',
        \MacropaySolutions\Kernel\Contracts\Encryption\Encrypter::class => 'registerEncrypterBindings',
        'events' => 'registerEventBindings',
        \MacropaySolutions\Kernel\Contracts\Events\Dispatcher::class => 'registerEventBindings',
        'files' => 'registerFilesBindings',
        'hash' => 'registerHashBindings',
        \MacropaySolutions\Kernel\Contracts\Hashing\Hasher::class => 'registerHashBindings',
        'log' => 'registerLogBindings',
        \Psr\Log\LoggerInterface::class => 'registerLogBindings',
        'queue' => 'registerQueueBindings',
        'queue.connection' => 'registerQueueBindings',
        \MacropaySolutions\Kernel\Contracts\Queue\Factory::class => 'registerQueueBindings',
        \MacropaySolutions\Kernel\Contracts\Queue\Queue::class => 'registerQueueBindings',
        'router' => 'registerRouterBindings',
        \Psr\Http\Message\ServerRequestInterface::class => 'registerPsrRequestBindings',
        \Psr\Http\Message\ResponseInterface::class => 'registerPsrResponseBindings',
        'translator' => 'registerTranslationBindings',
        'url' => 'registerUrlGeneratorBindings',
        'validator' => 'registerValidatorBindings',
        \MacropaySolutions\Kernel\Contracts\Validation\Factory::class => 'registerValidatorBindings',
        'view' => 'registerViewBindings',
        \MacropaySolutions\Kernel\Contracts\View\Factory::class => 'registerViewBindings',
        'view.finder' => 'registerViewBindings',
        'template.compiler' => 'registerViewBindings',
        'view.engine.resolver' => 'registerViewBindings',
        \MacropaySolutions\Kernel\View\Engines\EngineResolver::class => 'registerViewBindings',

        'session' => 'registerSessionBindings',
        'session.store' => 'registerSessionBindings',
        \MacropaySolutions\Kernel\Session\Middleware\StartSession::class => 'registerSessionBindings',

        'cookie' => 'registerCookieBindings',
    ];

    /**
     * @inheritDoc
     */
    public function bootstrapPath($path = ''): string
    {
        return join_paths($this->basePath('bootstrap'), $path);
    }

    /**
     * @inheritDoc
     */
    public function publicPath($path = ''): string
    {
        return join_paths($this->basePath('public'), $path);
    }

    /**
     * @inheritDoc
     */
    public function hasDebugModeEnabled(): bool
    {
        return (bool)$this->make('config')->get('app.debug');
    }

    /**
     * @inheritDoc
     */
    public function maintenanceMode(): MaintenanceModeContract
    {
        return new class () implements MaintenanceModeContract {
            /**
             * @inheritDoc
             */
            public function activate(array $payload): void
            {
                throw new \Exception('Unsupported');
            }

            /**
             * @inheritDoc
             */
            public function deactivate(): void
            {
            }

            /**
             * @inheritDoc
             */
            public function active(): bool
            {
                return false;
            }

            /**
             * @inheritDoc
             */
            public function data(): array
            {
                return [];
            }
        };
    }

    /**
     * @inheritDoc
     */
    public function registerConfiguredProviders(): void
    {
    }

    /**
     * @inheritDoc
     */
    public function resolveProvider($provider)
    {
        return new $provider($this);
    }

    /**
     * @inheritDoc
     */
    public function booting($callback): void
    {
    }

    /**
     * @inheritDoc
     */
    public function booted($callback): void
    {
    }

    /**
     * @inheritDoc
     */
    public function bootstrapWith(array $bootstrappers): void
    {
        $this->hasBeenBootstrapped = true;
    }

    /**
     * @inheritDoc
     */
    public function getProviders($provider): array
    {
        $name = \is_string($provider) ? $provider : \get_class($provider);

        return Arr::where($this->loadedProviders, fn(mixed $value): bool => $value instanceof $name);
    }

    /**
     * @inheritDoc
     */
    public function hasBeenBootstrapped(): bool
    {
        return $this->hasBeenBootstrapped;
    }

    /**
     * @inheritDoc
     */
    public function loadDeferredProviders(): void
    {
    }

    /**
     * Get the fully qualified path to the environment file.
     */
    public function environmentFilePath(): string
    {
        return $this->basePath('.env');
    }
}
