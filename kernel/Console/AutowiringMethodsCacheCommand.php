<?php

namespace MacropaySolutions\Kernel\Console;

use Composer\Autoload\ClassLoader;
use MacropaySolutions\Kernel\Container\BoundMethod;
use MacropaySolutions\Kernel\Filesystem\Filesystem;
use MacropaySolutions\Kernel\Queue\CallQueuedHandler;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'autowiring:cache')]
class AutowiringMethodsCacheCommand extends Command
{
    use \MacropaySolutions\Framework\Traitables\MacropaySolutionsKernelConsoleAutowiringMethodsCacheCommand;

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
        $this->callSilent('list');
        $this->callSilent('autowiring:clear');

        foreach ($this->app->getAvailableBindings() as $binding => $resolver) {
            try {
                $this->app->make($binding);
            } catch (\Throwable $e) {
                $this->info($this->signature . ' notice for availableBinding ' . $binding . ': ' . $e->getMessage());
            }
        }

        $cacheDir = $this->app->bootstrapPath('cache' . DIRECTORY_SEPARATOR . 'autowiring');

        $this->files->ensureDirectoryExists($cacheDir);

        $preloadClasses = [];

        foreach ($this->getMap() as $fqn => $methods) {
            if (\str_contains($fqn, "\0") || \str_contains($fqn, '@anonymous')) {
                continue;
            }

            $safeClassName = BoundMethod::getHashedAutowireClass($fqn);

            // the autowired classes may live outside these namespaces
            // and newly generated ones are not in the class map yet
            \array_push($preloadClasses, $fqn, 'MacropaySolutions\\Framework\\Autowiring\\' . $safeClassName);

            $exportedArray = \var_export($methods, true);

            $phpContent = <<<PHP
<?php

namespace MacropaySolutions\Framework\Autowiring;

class {$safeClassName}
{
    public const MAP = {$exportedArray};
}
PHP;

            \file_put_contents($cacheDir . DIRECTORY_SEPARATOR . $safeClassName . '.php', $phpContent);
        }

        \file_put_contents(
            $this->app->getCachedAutowiringPath(),
            '<?php return [
    \MacropaySolutions\Kernel\Container\BoundMethod::MACROPAY_SOLUTIONS_FRAMEWORK_AUTOWIRING_NS => true
];'
        );

        if ([] !== $this->app::getAbstractToTypeOfResolvingCallbacksEventsAsKeys()) {
            \file_put_contents(
                $this->app->getCachedAbstractToTypeOfResolvingCallbacksEventsAsKeysPath(),
                '<?php return ' .
                    \var_export($this->app::getAbstractToTypeOfResolvingCallbacksEventsAsKeys(), true) . ';'
            );
        }

        $this->info('Autowiring cached successfully.');

        if (false !== \config('app.preload', true)) {
            $this->cachePreload($preloadClasses);
        }
    }

    /**
     * Writes bootstrap/cache/preload/preload.php for opcache.preload with the classes that load without a fatal error.
     */
    private function cachePreload(array $autowiredClasses): void
    {
        $vendorDir = $this->getComposerVendorDir();

        if (null === $vendorDir) {
            $this->info('Preload skipped: no Composer autoloader loads this application.');

            return;
        }

        $preloadDir = $this->app->bootstrapPath('cache' . DIRECTORY_SEPARATOR . 'preload');

        $this->files->ensureDirectoryExists($preloadDir);

        $candidates = [];

        foreach (\array_keys(ClassLoader::getRegisteredLoaders()[$vendorDir]->getClassMap()) as $class) {
            // their vendor parents are preloaded through autoloading; all vendor was slower in benchmarks
            if (\str_starts_with($class, 'MacropaySolutions\\') || \str_starts_with($class, 'App\\')) {
                $candidates[] = $class;
            }
        }

        // the autowired classes may live outside these namespaces and the generated ones are not in the class map yet
        $classes = $this->getPreloadableClasses(
            \array_values(\array_unique(\array_merge($candidates, $autowiredClasses))),
            $autoload = $vendorDir . DIRECTORY_SEPARATOR . 'autoload.php'
        );

        $base = \rtrim($this->app->basePath(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        // a path relative to this file keeps working when the release is moved after the build
        $autoloadExport = \str_starts_with($preloadDir, $base) && \str_starts_with($autoload, $base)
            ? '__DIR__ . ' . \var_export(
                DIRECTORY_SEPARATOR . \str_repeat('..' . DIRECTORY_SEPARATOR, \substr_count(
                    \substr($preloadDir, \strlen($base)),
                    DIRECTORY_SEPARATOR
                ) + 1) . \substr($autoload, \strlen($base)),
                true
            )
            : \var_export($autoload, true);
        $classesExport = \var_export($classes, true);

        \file_put_contents(
            $preloadDir . DIRECTORY_SEPARATOR . 'preload.php',
            <<<PHP
<?php

// Generated by autowiring:cache. Every class listed here was loaded in a separate process without a fatal error.

// preload helps long-lived PHP-FPM workers; a CLI process would only pay for it at every start
if ('cli' === \\PHP_SAPI) {
    return;
}

require {$autoloadExport};

foreach ({$classesExport} as \$class) {
    try {
        // the final class map decides the file, so the traits generated by macro:cache win over the shipped stubs
        \\class_exists(\$class);
    } catch (\\Throwable) {
    }
}

PHP
        );

        $this->info('Preload cached for ' . \count($classes) . ' classes.');
    }

    /**
     * The vendor dir is not always basePath/vendor: a custom vendor-dir, or an app run with another vendor.
     */
    private function getComposerVendorDir(): ?string
    {
        if (!\class_exists(ClassLoader::class, false)) {
            return null;
        }

        foreach (ClassLoader::getRegisteredLoaders() as $vendorDir => $loader) {
            if (false !== $loader->findFile(self::class)) {
                return $vendorDir;
            }
        }

        return null;
    }

    /**
     * A missing trait is a fatal error no try can catch, so the classes are loaded in a child process.
     * A child that dies reports the class it was loading; the next child continues after it.
     */
    private function getPreloadableClasses(array $classes, string $autoload): array
    {
        $result = [];

        while ([] !== $classes) {
            $report = $this->loadClassesInChildProcess($classes, $autoload);

            if (null === $report) {
                // no report (killed or crashed before its shutdown function ran): halve until the culprit is alone
                if (1 === \count($classes)) {
                    return $result;
                }

                $half = \intdiv(\count($classes), 2);

                return \array_merge(
                    $result,
                    $this->getPreloadableClasses(\array_slice($classes, 0, $half), $autoload),
                    $this->getPreloadableClasses(\array_slice($classes, $half), $autoload)
                );
            }

            // a class that throws (missing parent or interface) is not preloadable and would only log a warning
            \array_push(
                $result,
                ...\array_diff(\array_slice($classes, 0, $report['done']), $report['failed'])
            );
            // done is the index of the class that killed the child, or the list size when it finished
            $classes = \array_slice($classes, $report['done'] + 1);
        }

        return $result;
    }

    /**
     * Returns how far the child got and which classes threw, or null when it gave no report.
     */
    private function loadClassesInChildProcess(array $classes, string $autoload): ?array
    {
        $listFile = \tempnam(\sys_get_temp_dir(), 'preload');
        \file_put_contents($listFile, \json_encode($classes));
        $nullDevice = '\\' === DIRECTORY_SEPARATOR ? 'NUL' : '/dev/null';

        $process = \proc_open(
            [
                \PHP_BINARY,
                '-d',
                'memory_limit=-1',
                '-r',
                '$f = $argv[2]; $c = \json_decode(\file_get_contents($f), true); $d = 0; $x = []; ' .
                    'require $argv[1]; ' .
                    // a shutdown function runs after a fatal error too, so it tells where the child died
                    '\register_shutdown_function(static function () use (&$d, &$x, $f): void ' .
                    '{ \file_put_contents($f, \json_encode([\'done\' => $d, \'failed\' => $x])); }); ' .
                    'foreach ($c as $i => $n) { $d = $i; ' .
                    'try { \class_exists($n); } catch (\Throwable) { $x[] = $n; } } ' .
                    '$d = \count($c);',
                $autoload,
                $listFile,
            ],
            [0 => ['file', $nullDevice, 'r'], 1 => ['file', $nullDevice, 'w'], 2 => ['file', $nullDevice, 'w']],
            $pipes,
            $this->app->basePath()
        );

        try {
            if (!\is_resource($process)) {
                return null;
            }

            \proc_close($process);
            $report = \json_decode((string)\file_get_contents($listFile), true);

            // without a shutdown report the file still holds the class list
            return \is_array($report) && isset($report['done'], $report['failed']) ? $report : null;
        } finally {
            \unlink($listFile);
        }
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
                'path' => $path . DIRECTORY_SEPARATOR . 'Events',
                'methods' => [],
            ],
            [
                'path' => $path . DIRECTORY_SEPARATOR . 'CallablesAsArray',
                'methods' => ['*'],
            ],
            [
                'path' => $path . DIRECTORY_SEPARATOR . 'View' . DIRECTORY_SEPARATOR . 'Components',
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
                \MacropaySolutions\Kernel\Http\Response::class,
                \Symfony\Component\HttpFoundation\JsonResponse::class,
                \MacropaySolutions\Kernel\Http\JsonResponse::class,
                \MacropaySolutions\CrufdWizard\Responses\DecoratableJsonResponse::class,
                \MacropaySolutions\CrufadWizard\Responses\DecoratableJsonResponse::class,
                \Symfony\Component\HttpFoundation\BinaryFileResponse::class,
                \Symfony\Component\HttpFoundation\StreamedResponse::class,
                \Symfony\Component\HttpFoundation\StreamedJsonResponse::class,
                \Symfony\Component\HttpFoundation\RedirectResponse::class,
                \MacropaySolutions\Kernel\Http\RedirectResponse::class,
                CallQueuedHandler::class,
                \MacropaySolutions\CrufdWizard\Responses\StreamedJsonResponse::class,
                \MacropaySolutions\CrufadWizard\Responses\StreamedJsonResponse::class,
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
                \MacropaySolutions\Kernel\Http\UploadedFile::class,
                \MacropaySolutions\Kernel\Mail\Attachment::class,
                \MacropaySolutions\Kernel\Mail\Mailable::class,
                \MacropaySolutions\Kernel\Mail\Mailer::class,
                \MacropaySolutions\Kernel\Process\Factory::class,
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
                \MacropaySolutions\CrufadWizard\Obvious\CustomRelations\HasManySelfThroughSelf::class,
                \MacropaySolutions\CrufadWizard\Obvious\CustomRelations\HasManyThrough2LinkTables::class,
                \MacropaySolutions\CrufadWizard\Obvious\CustomRelations\HasManyThrough3LinkTables::class,
                \MacropaySolutions\CrufadWizard\Obvious\CustomRelations\HasOneSelfThroughSelf::class,
                \MacropaySolutions\CrufadWizard\Obvious\CustomRelations\HasOneThrough2LinkTables::class,
                \MacropaySolutions\CrufadWizard\Obvious\CustomRelations\HasOneThrough3LinkTables::class,
                \MacropaySolutions\Kernel\Database\Query\JoinLateralClause::class,
                \MacropaySolutions\Kernel\Database\Query\JoinClause::class,
                \MacropaySolutions\Kernel\Database\Schema\SqlServerBuilder::class,
                \MacropaySolutions\Kernel\Database\Schema\PostgresBuilder::class,
                \MacropaySolutions\Kernel\Database\Schema\MySqlBuilder::class,
                \MacropaySolutions\Kernel\Database\Schema\MariaDbBuilder::class,
                \MacropaySolutions\Kernel\Database\Schema\SQLiteBuilder::class,
                \MacropaySolutions\Kernel\Database\SQLiteConnection::class,
                \MacropaySolutions\Kernel\Database\SqlServerConnection::class,
                \MacropaySolutions\Kernel\Database\MySqlConnection::class,
                \MacropaySolutions\Kernel\Database\MariaDbConnection::class,
                \MacropaySolutions\Kernel\Database\PostgresConnection::class,
                \MacropaySolutions\Kernel\Database\Query\Grammars\Grammar::class,
                \MacropaySolutions\Kernel\Database\Query\Grammars\MySqlGrammar::class,
                \MacropaySolutions\Kernel\Database\Query\Grammars\MariaDbGrammar::class,
                \MacropaySolutions\Kernel\Database\Query\Grammars\PostgresGrammar::class,
                \MacropaySolutions\Kernel\Database\Query\Grammars\SQLiteGrammar::class,
                \MacropaySolutions\Kernel\Database\Query\Grammars\SqlServerGrammar::class,
                \MacropaySolutions\Kernel\Database\Schema\Grammars\Grammar::class,
                \MacropaySolutions\Kernel\Database\Schema\Grammars\MySqlGrammar::class,
                \MacropaySolutions\Kernel\Database\Schema\Grammars\MariaDbGrammar::class,
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
                \MacropaySolutions\Kernel\Validation\NotPwnedVerifier::class,
            ] as $class
        ) {
            try {
                if (\class_exists($class) && (new \ReflectionClass($class))->isInstantiable()) {
                    $map[$class] = []; // __construct is implicit
                }
            } catch (\Throwable) {
            }
        }

        $globalMiddleware = ($frameworkReflector = new \ReflectionClass($this->app))
            ->getProperty('middleware')
            ->getValue($this->app);

        foreach ($globalMiddleware as $middleware) {
            try {
                if (
                    \is_string($middleware)
                    && \class_exists($middleware)
                    && (new \ReflectionClass($middleware))->isInstantiable()
                ) {
                    $class = \ltrim($middleware, '\\');
                    $map[$class] = \array_unique(\array_merge($map[$class] ?? [], ['handle']));
                }
            } catch (\Throwable) {
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
                        try {
                            if (
                                \is_string($class)
                                && \class_exists($class)
                                && (new \ReflectionClass($class))->isInstantiable()
                            ) {
                                $class = \ltrim($class, '\\');
                                $map[$class] = \array_unique(\array_merge($map[$class] ?? [], ['handle']));
                            }
                        } catch (\Throwable) {
                        }
                    }
                }
            }

            if (isset($route['action']['uses'])) {
                $parts = \explode('@', $route['action']['uses']);
                $controller = \reset($parts);
                $method = \next($parts);

                try {
                if (
                    \is_string($controller)
                    && $controller !== 'Closure'
                    && \class_exists($controller)
                    && (new \ReflectionClass($controller))->isInstantiable()
                ) {
                        if (!\is_string($method) || $method === '') {
                            $method = '__invoke';
                        }

                        $map[$controller = \ltrim($controller, '\\')] =
                            \array_unique(\array_merge($map[$controller] ?? [], [$method]));
                    }
                } catch (\Throwable) {
                }
            }
        }

        return $map;
    }
}
