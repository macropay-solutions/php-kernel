<?php

namespace MacropaySolutions\Kernel\Console;

use MacropaySolutions\Kernel\Container\BoundMethod;
use MacropaySolutions\Kernel\Container\Util;
use MacropaySolutions\Kernel\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

class DiscoverAutowiring
{
    /**
     * The callback to be used to guess class names.
     *
     * @var callable(SplFileInfo, string): string|null
     */
    public static $guessClassNamesUsingCallback;

    /**
     * @param array $autowiringPathAndMethods [
     *     'path' => string,
     *     'methods' => string[],
     *  ]
     */
    public static function within(array $autowiringPathAndMethods, string $basePath): array
    {
        $path = $autowiringPathAndMethods['path'];

        if (\is_dir($path)) {
            return static::getMethods(
                Finder::create()->files()->in($path),
                $basePath,
                \array_flip($autowiringPathAndMethods['methods'] ?? [])
            );
        }

        if (!\class_exists($path)) {
            return [];
        }

        return static::getMethods(
            [$path],
            $basePath,
            \array_flip($autowiringPathAndMethods['methods'] ?? [])
        );
    }

    /**
     * @param array $fqnToMethodsMap [
     *     '{fqn}' => string[],
     *  ]
     */
    public static function inFqnToMethodsMap(array $fqnToMethodsMap): array
    {
        $map = [];

        foreach ($fqnToMethodsMap as $fqn => $methods) {
            $map = static::getMethods([$fqn], '', \array_flip((array)$methods), $map);
        }

        return static::getMethods(
            \array_keys(BoundMethod::getClassesFqnsToCacheForAutowire()),
            '',
            ['__construct' => null],
            $map
        );
    }

    /**
     * Get all the listeners and their corresponding events.
     *
     * @return array [
     *    '{classFqn}' => [
     *      '{methodName}' => [
     *        '{paramName1}' => [
     *          'c' => string, // can not exist
     *          'v' => bool, // can not exist
     *          'o' => bool, // can not exist
     *          'd' => mixed, // can not exist
     *        ],
     *         '{paramName2}' => [
     *           'c' => string, // can not exist
     *           'v' => bool, // can not exist
     *           'o' => bool, // can not exist
     *           'd' => mixed, // can not exist
     *         ],
     *         ...
     *      ],
     *      ...
     *    ]
     *  ]
     */
    protected static function getMethods(
        Finder|array $classes,
        string $basePath,
        array $methods = [],
        array $classMethodMap = [],
    ): array {
        $methods['__construct'] ??= true;
        $app = \app();
        $bindings = $app->getBindings();

        foreach ($classes as $splFileInfo) {
            try {
                $classFqn = $splFileInfo instanceof SplFileInfo ?
                    static::classFromFile($splFileInfo, $basePath) :
                    \ltrim($splFileInfo, '\\');
                $missingMethods = \array_diff_key($methods, $classMethodMap[$classFqn] ?? []);

                if ([] === $missingMethods) {
                    continue;
                }

                try {
                    $reflectionClass = new ReflectionClass($classFqn);
                } catch (\Throwable) {
                    continue;
                }

                if (!$reflectionClass->isInstantiable()) {
                    continue;
                }

                $classMethodMap[$classFqn]['__construct'] ??= [];
                $all = isset($methods['*']);

                foreach ($reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                    if (
                        !isset($missingMethods[$methodName = $method->getName()])
                        && !$all
                    ) {
                        continue;
                    }

                    $reflectionParameters = $method->getParameters();
                    $classMethodMap[$classFqn][$methodName] = [];

                    foreach ($reflectionParameters as $reflectionParameter) {
                        $classMethodMap[$classFqn][$methodName][$reflectionParameter->getName()] =
                            self::getParameterDetails($reflectionParameter);
                    }
                }

                /** avoid polluting cache with dead strings */
                if (isset($bindings[$classFqn]) || isset($bindings[$app->getAlias($classFqn)])) {
                    unset($classMethodMap[$classFqn]['__construct']);

                    if ($classMethodMap[$classFqn] === []) {
                        unset($classMethodMap[$classFqn]);
                    }
                }
            } catch (\Throwable $e) {
                $failedTarget = $classFqn
                    ?? ($splFileInfo instanceof SplFileInfo ? $splFileInfo->getFilename() : $splFileInfo);

                \app('log')->info('Autowiring notice for classFqn ' . $failedTarget . ': ' . $e->getMessage());
            }
        }

        return $classMethodMap;
    }

    /**
     * Extract the class name from the given file path.
     */
    protected static function classFromFile(SplFileInfo $file, string $basePath): string
    {
        if (static::$guessClassNamesUsingCallback) {
            return call_user_func(static::$guessClassNamesUsingCallback, $file, $basePath);
        }

        $class = trim(Str::replaceFirst($basePath, '', $file->getRealPath()), DIRECTORY_SEPARATOR);

        return str_replace(
            [DIRECTORY_SEPARATOR, ucfirst(basename(app()->path())) . '\\'],
            ['\\', app()->getNamespace()],
            ucfirst(Str::replaceLast('.php', '', $class))
        );
    }

    /**
     * Specify a callback to be used to guess class names.
     *
     * @param callable(SplFileInfo, string): string $callback
     */
    public static function guessClassNamesUsing(callable $callback): void
    {
        static::$guessClassNamesUsingCallback = $callback;
    }

    public static function getParameterDetails(\ReflectionParameter $reflectionParameter): array
    {
        return (\is_string($c = Util::getParameterClassName($reflectionParameter)) ? ['c' => $c] : [])
            + ($reflectionParameter->isVariadic() ? ['v' => true] : [])
            + ($reflectionParameter->isOptional() ? ['o' => true] : [])
            + ($reflectionParameter->isDefaultValueAvailable() ? [
                'd' => $reflectionParameter->getDefaultValue()
            ] : []);
    }
}
