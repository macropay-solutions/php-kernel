<?php

namespace Illuminate\Console;

use Illuminate\Container\BoundMethod;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Queue\CallQueuedHandler;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
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
                \Symfony\Component\HttpFoundation\Response::class,
                \Illuminate\Http\Response::class,
                \Symfony\Component\HttpFoundation\JsonResponse::class,
                \Illuminate\Http\JsonResponse::class,
                MacropaySolutions\CrufdWizard\Responses\DecoratableJsonResponse::class,
                MacropaySolutions\RestWizard\Responses\DecoratableJsonResponse::class,
                \Symfony\Component\HttpFoundation\BinaryFileResponse::class,
                \Symfony\Component\HttpFoundation\StreamedResponse::class,
                \Symfony\Component\HttpFoundation\StreamedJsonResponse::class,
                \Symfony\Component\HttpFoundation\RedirectResponse::class,
                \Illuminate\Http\RedirectResponse::class,
                CallQueuedHandler::class,
                \MacropaySolutions\CrufdWizard\Responses\StreamedJsonResponse::class,
                \MacropaySolutions\RestWizard\Responses\StreamedJsonResponse::class,
                \MacropaySolutions\Framework\Http\ResponseFactory::class,
                \Illuminate\Auth\RequestGuard::class,
                \Illuminate\Auth\SessionGuard::class,
                \Illuminate\Cache\Repository::class,
                \Illuminate\Support\Arr::class,
                \Illuminate\Support\Collection::class,
                \Illuminate\Support\LazyCollection::class,
                \Illuminate\Config\Repository::class,
                \Illuminate\Console\Scheduling\Event::class,
                \Illuminate\Console\Scheduling\Schedule::class,
                \Illuminate\Console\Command::class,
                \Illuminate\Cookie\CookieJar::class,
                \Illuminate\Database\Query\Builder::class,
                \Illuminate\Database\Schema\Blueprint::class,
                \Illuminate\Database\Schema\Builder::class,
                \Illuminate\Database\Connection::class,
                \Illuminate\Database\DatabaseManager::class,
                \Illuminate\Filesystem\Filesystem::class,
                \Illuminate\Filesystem\FilesystemAdapter::class,
                \Illuminate\Http\Client\Factory::class,
                \Illuminate\Http\Client\PendingRequest::class,
                \Illuminate\Http\Client\Request::class,
                \Illuminate\Http\Client\Response::class,
                \Illuminate\Http\Client\ResponseSequence::class,
                \Illuminate\Http\Resources\Json\JsonResource::class,
                \Illuminate\Http\Resources\Json\ResourceCollection::class,
                \Illuminate\Http\Resources\Json\AnonymousResourceCollection::class,
                \Illuminate\Http\UploadedFile::class,
                \Illuminate\Mail\Attachment::class,
                \Illuminate\Mail\Mailable::class,
                \Illuminate\Mail\Mailer::class,
                \Illuminate\Process\Factory::class,
                \Illuminate\Routing\PendingResourceRegistration::class,
                \Illuminate\Routing\PendingSingletonResourceRegistration::class,
                \Illuminate\Routing\Redirector::class,
                \Illuminate\Routing\ResponseFactory::class,
                \Illuminate\Routing\Route::class,
                \Illuminate\Routing\Router::class,
                \Illuminate\Routing\UrlGenerator::class,
                \Illuminate\Session\Store::class,
                \Illuminate\Support\Number::class,
                \Illuminate\Support\Optional::class,
                \Illuminate\Support\Sleep::class,
                \Illuminate\Support\Str::class,
                \Illuminate\Support\Stringable::class,
                \Illuminate\Translation\Translator::class,
                \Illuminate\Validation\Rules\File::class,
                \Illuminate\Validation\Rule::class,
                \Illuminate\View\ComponentAttributeBag::class,
                \Illuminate\View\Factory::class,
                \Illuminate\View\View::class,
                \Illuminate\Cache\TaggedCache::class,
                \Illuminate\Routing\SortedMiddleware::class,
                \Illuminate\Database\Eloquent\Collection::class,
                \Illuminate\Notifications\DatabaseNotificationCollection::class,
                \MacropaySolutions\KernelDev\Testing\LoggedExceptionCollection::class,
                \Illuminate\Console\Scheduling\CallbackEvent::class,
                \Illuminate\Database\Eloquent\Relations\BelongsTo::class,
                \Illuminate\Database\Eloquent\Relations\BelongsToMany::class,
                \Illuminate\Database\Eloquent\Relations\HasMany::class,
                \Illuminate\Database\Eloquent\Relations\HasManyThrough::class,
                \Illuminate\Database\Eloquent\Relations\HasOne::class,
                \Illuminate\Database\Eloquent\Relations\HasOneOrMany::class,
                \Illuminate\Database\Eloquent\Relations\HasOneThrough::class,
                \Illuminate\Database\Eloquent\Relations\MorphMany::class,
                \Illuminate\Database\Eloquent\Relations\MorphOne::class,
                \Illuminate\Database\Eloquent\Relations\MorphOneOrMany::class,
                \Illuminate\Database\Eloquent\Relations\MorphTo::class,
                \Illuminate\Database\Eloquent\Relations\MorphToMany::class,
                \MacropaySolutions\RestWizard\Eloquent\CustomRelations\HasManySelfThroughSelf::class,
                \MacropaySolutions\RestWizard\Eloquent\CustomRelations\HasManyThrough2LinkTables::class,
                \MacropaySolutions\RestWizard\Eloquent\CustomRelations\HasManyThrough3LinkTables::class,
                \MacropaySolutions\RestWizard\Eloquent\CustomRelations\HasOneSelfThroughSelf::class,
                \MacropaySolutions\RestWizard\Eloquent\CustomRelations\HasOneThrough2LinkTables::class,
                \MacropaySolutions\RestWizard\Eloquent\CustomRelations\HasOneThrough3LinkTables::class,
                \Illuminate\Database\Query\JoinLateralClause::class,
                \Illuminate\Database\Query\JoinClause::class,
                \Illuminate\Database\Schema\SqlServerBuilder::class,
                \Illuminate\Database\Schema\PostgresBuilder::class,
                \Illuminate\Database\Schema\MySqlBuilder::class,
                \Illuminate\Database\Schema\SQLiteBuilder::class,
                \Illuminate\Database\SQLiteConnection::class,
                \Illuminate\Database\SqlServerConnection::class,
                \Illuminate\Database\MySqlConnection::class,
                \Illuminate\Database\PostgresConnection::class,
                \Illuminate\Database\Query\Grammars\Grammar::class,
                \Illuminate\Database\Query\Grammars\MySqlGrammar::class,
                \Illuminate\Database\Query\Grammars\PostgresGrammar::class,
                \Illuminate\Database\Query\Grammars\SQLiteGrammar::class,
                \Illuminate\Database\Query\Grammars\SqlServerGrammar::class,
                \Illuminate\Database\Schema\Grammars\Grammar::class,
                \Illuminate\Database\Schema\Grammars\MySqlGrammar::class,
                \Illuminate\Database\Schema\Grammars\PostgresGrammar::class,
                \Illuminate\Database\Schema\Grammars\SQLiteGrammar::class,
                \Illuminate\Database\Schema\Grammars\SqlServerGrammar::class,
                \Illuminate\Filesystem\AwsS3V3Adapter::class,
                \Illuminate\Http\FormRequest::class,
                \App\FormRequest::class,
                \Illuminate\Mail\Mailables\Attachment::class,
                \Illuminate\Redis\Connections\PhpRedisClusterConnection::class,
                \Illuminate\Redis\Connections\PhpRedisConnection::class,
                \Illuminate\Redis\Connections\PredisClusterConnection::class,
                \Illuminate\Redis\Connections\PredisConnection::class,
                \Illuminate\Session\EncryptedStore::class,
                \Illuminate\Validation\Rules\ImageFile::class,
                \Illuminate\Auth\Listeners\SendEmailVerificationNotification::class,
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
