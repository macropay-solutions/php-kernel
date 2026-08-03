<?php

namespace MacropaySolutions\Kernel\Console;

use MacropaySolutions\Kernel\Container\BoundMethod;
use MacropaySolutions\Kernel\Filesystem\Filesystem;
use MacropaySolutions\Kernel\Queue\CallQueuedHandler;
use MacropaySolutions\Kernel\Routing\Router;
use MacropaySolutions\Kernel\Support\ServiceProvider;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'autowiring:cache')]
class AutowiringMethodsCacheCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'autowiring:cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a autowiring cache file for faster methods autowiring';

    protected Filesystem $files;

    /**
     * Create a new route command instance.
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();

        $this->files = $files;
    }

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        BoundMethod::enableClassesFqnsToCacheForAutowire();
        $this->callSilent('list');
        $this->callSilent('autowiring:clear');

        if ($this->app instanceof \MacropaySolutions\Framework\Application) {
            foreach ($this->app->availableBindings as $binding => $resolver) {
                try {
                    $this->app->make($binding);
                } catch (\Throwable $e) {
                    $this->info($this->signature . ' notice for availableBinding ' . $binding . ': ' .
                        $e->getMessage());
                }
            }
        }

        \file_put_contents(
            $this->app->getCachedAutowiringPath(),
            '<?php return ' . \var_export($this->getMap(), true) . ';'
        );

        if ([] !== $this->app::getAbstractToTypeOfResolvingCallbacksEventsAsKeys()) {
            \file_put_contents(
                $this->app->getCachedAbstractToTypeOfResolvingCallbacksEventsAsKeysPath(),
                '<?php return ' .
                    \var_export($this->app::getAbstractToTypeOfResolvingCallbacksEventsAsKeys(), true) . ';'
            );
        }

        $this->components->info('Autowiring cached successfully.');
    }

    protected function getMap(): array
    {
        return \array_replace_recursive(
            \collect($this->getPathsAndMethods())
                ->reject(fn(array $pathsAndMethods): bool =>
                        '' === (string)($pathsAndMethods['path'] ?? '') || (
                            !\is_dir($pathsAndMethods['path'])
                            && !\class_exists($pathsAndMethods['path'])
                        ))
                ->reduce(
                    fn(array $discovered, array $pathsAndMethods): array => \array_replace_recursive(
                        $discovered,
                        DiscoverAutowiring::within($pathsAndMethods, $this->app->basePath())
                    ),
                    []
                ),
            DiscoverAutowiring::inFqnToMethodsMap($this->getFqnToMethodsMap()),
        );
    }

    private function getPathsAndMethods(): array
    {
        $basePaths = \config('app.autowiring') ?? [
            [
                'path' => ($path = \app()->path()) . DIRECTORY_SEPARATOR . 'Console' . DIRECTORY_SEPARATOR . 'Commands',
                'methods' => ['handle', '__invoke'],
            ],
            [
                'path' => $path . DIRECTORY_SEPARATOR . 'Jobs',
                'methods' => ['handle', '__invoke'],
            ],
            [
                'path' => $path . DIRECTORY_SEPARATOR . 'Http' . DIRECTORY_SEPARATOR . 'Requests',
                'methods' => ['validator', 'authorize', 'after', 'rules'],
            ],
            [
                'path' => $path . DIRECTORY_SEPARATOR . 'Listeners',
                'methods' => [],
            ],
            [
                'path' => $path . DIRECTORY_SEPARATOR . 'CallablesAsArray',
                'methods' => ['*'],
            ],
        ];

        return \array_merge($basePaths, $this->getVendorPackagePaths());
    }

    /**
     * Scans Composer's compiled JSON file for packages declaring autowiring paths
     */
    private function getVendorPackagePaths(): array
    {
        $paths = [];
        $installedJson = $this->app->basePath('vendor' . DIRECTORY_SEPARATOR . 'composer' . DIRECTORY_SEPARATOR .
            'installed.json');

        if (\file_exists($installedJson)) {
            $data = \json_decode(\file_get_contents($installedJson), true);

            // Handle both Composer 2.x (wraps in 'packages') and Composer 1.x (flat array)
            $packages = $data['packages'] ?? $data ?? [];

            foreach ($packages as $package) {
                if (isset($package['extra']['php-framework']['autowiring'])) {
                    // Strictly convert Composer's "vendor/package" format to native separators
                    $packageDir = $this->app->basePath('vendor') . DIRECTORY_SEPARATOR .
                        \str_replace('/', DIRECTORY_SEPARATOR, $package['name']);

                    foreach ($package['extra']['php-framework']['autowiring'] as $config) {
                        $rawPath = (string)($config['path'] ?? '');

                        if ('' === $rawPath) {
                            continue;
                        }

                        if (\class_exists($rawPath)) {
                            $paths[] = $config;

                            continue;
                        }

                        $resolvedPath = $packageDir . DIRECTORY_SEPARATOR .
                            \ltrim(\str_replace('/', DIRECTORY_SEPARATOR, $rawPath), DIRECTORY_SEPARATOR);

                        if (\is_dir($resolvedPath)) {
                            $paths[] = [
                                'path' => $resolvedPath,
                                'methods' => $config['methods'] ?? [],
                            ];
                        }
                    }
                }
            }
        }

        return $paths;
    }

    /**
     * @return array [
     *      '{fqn}' => string[],
     *   ]
     */
    private function getFqnToMethodsMap(): array
    {
        $map = [];

        foreach (
            [
                \MacropaySolutions\Kernel\Http\Base\Response::class,
                \MacropaySolutions\Kernel\Http\Response::class,
                \MacropaySolutions\Kernel\Http\Base\JsonResponse::class,
                \MacropaySolutions\Kernel\Http\JsonResponse::class,
                \MacropaySolutions\CrufdWizard\Responses\DecoratableJsonResponse::class,
                \MacropaySolutions\RestWizard\Responses\DecoratableJsonResponse::class,
                \MacropaySolutions\Kernel\Http\Base\BinaryFileResponse::class,
                \MacropaySolutions\Kernel\Http\Base\StreamedResponse::class,
                \MacropaySolutions\Kernel\Http\Base\StreamedJsonResponse::class,
                \Symfony\Component\HttpFoundation\RedirectResponse::class,
                \MacropaySolutions\Kernel\Http\RedirectResponse::class,
                CallQueuedHandler::class,
                \MacropaySolutions\CrufdWizard\Responses\StreamedJsonResponse::class,
                \MacropaySolutions\RestWizard\Responses\StreamedJsonResponse::class,
                \MacropaySolutions\Framework\Http\ResponseFactory::class,
                \MacropaySolutions\Kernel\Auth\RequestGuard::class,
                \MacropaySolutions\Kernel\Auth\SessionGuard::class,
                \MacropaySolutions\Kernel\Cache\Repository::class,
                \MacropaySolutions\Kernel\Support\Arr::class,
                \MacropaySolutions\Kernel\Support\Collection::class,
                \MacropaySolutions\Kernel\Support\LazyCollection::class,
                \MacropaySolutions\Kernel\Config\Repository::class,
                \MacropaySolutions\Kernel\Console\Scheduling\Event::class,
                \MacropaySolutions\Kernel\Console\Scheduling\Schedule::class,
                \MacropaySolutions\Kernel\Console\Command::class,
                \MacropaySolutions\Kernel\Cookie\CookieJar::class,
                \MacropaySolutions\Kernel\Database\Query\Builder::class,
                \MacropaySolutions\Kernel\Database\Schema\Blueprint::class,
                \MacropaySolutions\Kernel\Database\Schema\Builder::class,
                \MacropaySolutions\Kernel\Database\Connection::class,
                \MacropaySolutions\Kernel\Database\DatabaseManager::class,
                \MacropaySolutions\Kernel\Filesystem\Filesystem::class,
                \MacropaySolutions\Kernel\Filesystem\FilesystemAdapter::class,
                \MacropaySolutions\Kernel\Http\Client\Factory::class,
                \MacropaySolutions\Kernel\Http\Client\PendingRequest::class,
                \MacropaySolutions\Kernel\Http\Client\Request::class,
                \MacropaySolutions\Kernel\Http\Client\Response::class,
                \MacropaySolutions\Kernel\Http\Client\ResponseSequence::class,
                \MacropaySolutions\Kernel\Http\Resources\Json\JsonResource::class,
                \MacropaySolutions\Kernel\Http\Resources\Json\ResourceCollection::class,
                \MacropaySolutions\Kernel\Http\Resources\Json\AnonymousResourceCollection::class,
                \MacropaySolutions\Kernel\Http\UploadedFile::class,
                \MacropaySolutions\Kernel\Mail\Attachment::class,
                \MacropaySolutions\Kernel\Mail\Mailable::class,
                \MacropaySolutions\Kernel\Mail\Mailer::class,
                \MacropaySolutions\Kernel\Process\Factory::class,
                \MacropaySolutions\Kernel\Routing\PendingResourceRegistration::class,
                \MacropaySolutions\Kernel\Routing\PendingSingletonResourceRegistration::class,
                \MacropaySolutions\Kernel\Routing\Redirector::class,
                \MacropaySolutions\Kernel\Routing\ResponseFactory::class,
                \MacropaySolutions\Kernel\Routing\Route::class,
                \MacropaySolutions\Kernel\Routing\Router::class,
                \MacropaySolutions\Kernel\Routing\UrlGenerator::class,
                \MacropaySolutions\Kernel\Session\Store::class,
                \MacropaySolutions\Kernel\Support\Number::class,
                \MacropaySolutions\Kernel\Support\Optional::class,
                \MacropaySolutions\Kernel\Support\Sleep::class,
                \MacropaySolutions\Kernel\Support\Str::class,
                \MacropaySolutions\Kernel\Support\Stringable::class,
                \MacropaySolutions\Kernel\Translation\Translator::class,
                \MacropaySolutions\Kernel\Validation\Rules\File::class,
                \MacropaySolutions\Kernel\Validation\Rule::class,
                \MacropaySolutions\Kernel\View\ComponentAttributeBag::class,
                \MacropaySolutions\Kernel\View\Factory::class,
                \MacropaySolutions\Kernel\View\View::class,
                \MacropaySolutions\Kernel\Cache\TaggedCache::class,
                \MacropaySolutions\Kernel\Routing\SortedMiddleware::class,
                \MacropaySolutions\Kernel\Database\Obvious\Collection::class,
                \MacropaySolutions\Kernel\Notifications\DatabaseNotificationCollection::class,
                \MacropaySolutions\KernelDev\Testing\LoggedExceptionCollection::class,
                \MacropaySolutions\Kernel\Console\Scheduling\CallbackEvent::class,
                \MacropaySolutions\Kernel\Database\Obvious\Relations\BelongsTo::class,
                \MacropaySolutions\Kernel\Database\Obvious\Relations\BelongsToMany::class,
                \MacropaySolutions\Kernel\Database\Obvious\Relations\HasMany::class,
                \MacropaySolutions\Kernel\Database\Obvious\Relations\HasManyThrough::class,
                \MacropaySolutions\Kernel\Database\Obvious\Relations\HasOne::class,
                \MacropaySolutions\Kernel\Database\Obvious\Relations\HasOneOrMany::class,
                \MacropaySolutions\Kernel\Database\Obvious\Relations\HasOneThrough::class,
                \MacropaySolutions\Kernel\Database\Obvious\Relations\MorphMany::class,
                \MacropaySolutions\Kernel\Database\Obvious\Relations\MorphOne::class,
                \MacropaySolutions\Kernel\Database\Obvious\Relations\MorphOneOrMany::class,
                \MacropaySolutions\Kernel\Database\Obvious\Relations\MorphTo::class,
                \MacropaySolutions\Kernel\Database\Obvious\Relations\MorphToMany::class,
                \MacropaySolutions\RestWizard\Obvious\CustomRelations\HasManySelfThroughSelf::class,
                \MacropaySolutions\RestWizard\Obvious\CustomRelations\HasManyThrough2LinkTables::class,
                \MacropaySolutions\RestWizard\Obvious\CustomRelations\HasManyThrough3LinkTables::class,
                \MacropaySolutions\RestWizard\Obvious\CustomRelations\HasOneSelfThroughSelf::class,
                \MacropaySolutions\RestWizard\Obvious\CustomRelations\HasOneThrough2LinkTables::class,
                \MacropaySolutions\RestWizard\Obvious\CustomRelations\HasOneThrough3LinkTables::class,
                \MacropaySolutions\Kernel\Database\Query\JoinLateralClause::class,
                \MacropaySolutions\Kernel\Database\Query\JoinClause::class,
                \MacropaySolutions\Kernel\Database\Schema\SqlServerBuilder::class,
                \MacropaySolutions\Kernel\Database\Schema\PostgresBuilder::class,
                \MacropaySolutions\Kernel\Database\Schema\MySqlBuilder::class,
                \MacropaySolutions\Kernel\Database\Schema\SQLiteBuilder::class,
                \MacropaySolutions\Kernel\Database\SQLiteConnection::class,
                \MacropaySolutions\Kernel\Database\SqlServerConnection::class,
                \MacropaySolutions\Kernel\Database\MySqlConnection::class,
                \MacropaySolutions\Kernel\Database\PostgresConnection::class,
                \MacropaySolutions\Kernel\Database\Query\Grammars\Grammar::class,
                \MacropaySolutions\Kernel\Database\Query\Grammars\MySqlGrammar::class,
                \MacropaySolutions\Kernel\Database\Query\Grammars\PostgresGrammar::class,
                \MacropaySolutions\Kernel\Database\Query\Grammars\SQLiteGrammar::class,
                \MacropaySolutions\Kernel\Database\Query\Grammars\SqlServerGrammar::class,
                \MacropaySolutions\Kernel\Database\Schema\Grammars\Grammar::class,
                \MacropaySolutions\Kernel\Database\Schema\Grammars\MySqlGrammar::class,
                \MacropaySolutions\Kernel\Database\Schema\Grammars\PostgresGrammar::class,
                \MacropaySolutions\Kernel\Database\Schema\Grammars\SQLiteGrammar::class,
                \MacropaySolutions\Kernel\Database\Schema\Grammars\SqlServerGrammar::class,
                \MacropaySolutions\Kernel\Filesystem\AwsS3V3Adapter::class,
                \MacropaySolutions\Kernel\Http\FormRequest::class,
                \App\FormRequest::class,
                \MacropaySolutions\Kernel\Mail\Mailables\Attachment::class,
                \MacropaySolutions\Kernel\Redis\Connections\PhpRedisClusterConnection::class,
                \MacropaySolutions\Kernel\Redis\Connections\PhpRedisConnection::class,
                \MacropaySolutions\Kernel\Redis\Connections\PredisClusterConnection::class,
                \MacropaySolutions\Kernel\Redis\Connections\PredisConnection::class,
                \MacropaySolutions\Kernel\Session\EncryptedStore::class,
                \MacropaySolutions\Kernel\Validation\Rules\ImageFile::class,
                \MacropaySolutions\Kernel\Auth\Listeners\SendEmailVerificationNotification::class,
            ] as $class
        ) {
            if (\class_exists($class)) {
                $map[$class] = []; // __construct is implicit
            }
        }

        foreach ($this->app->getProviders(ServiceProvider::class) as $provider) {
            $map[$provider::class] = ['boot'];
        }

        if ($this->app instanceof \MacropaySolutions\Framework\Application) {
            $globalMiddleware = ($frameworkReflector = new \ReflectionClass($this->app))
                ->getProperty('middleware')
                ->getValue($this->app);

            foreach ($globalMiddleware as $middleware) {
                if (\is_string($middleware) && \class_exists($middleware)) {
                    $class = \ltrim($middleware, '\\');
                    $map[$class] = \array_unique(\array_merge($map[$class] ?? [], ['handle']));
                }
            }

            $routeMiddleware = $frameworkReflector->getProperty('routeMiddleware')->getValue($this->app);

            foreach ($this->app->router->getAllRoutes() as $route) {
                if (isset($route['action']['middleware'])) {
                    foreach ((array)$route['action']['middleware'] as $middleware) {
                        // Strip parameters (e.g. 'auth:api' -> 'auth')
                        $name = \strtok($middleware, ':');

                        $resolved = $routeMiddleware[$name] ?? $name;

                        foreach ((array) $resolved as $class) {
                            if (\is_string($class) && \class_exists($class)) {
                                $class = \ltrim($class, '\\');
                                $map[$class] = \array_unique(\array_merge($map[$class] ?? [], ['handle']));
                            }
                        }
                    }
                }

                if (isset($route['action']['uses'])) {
                    $parts = \explode('@', $route['action']['uses']);
                    $controller = \reset($parts);
                    $method = \next($parts);

                    if (\is_string($controller) && $controller !== 'Closure' && \class_exists($controller)) {
                        if (!\is_string($method) || $method === '') {
                            $method = '__invoke';
                        }

                        $map[$controller = \ltrim($controller, '\\')] =
                            \array_unique(\array_merge($map[$controller] ?? [], [$method]));
                    }
                }
            }
        }

        return $map;
    }
}
