<?php

namespace MacropaySolutions\Kernel\Console;

use MacropaySolutions\Kernel\Filesystem\Filesystem;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'macro:cache')]
class MacroCacheCommand extends Command
{
    use \MacropaySolutions\Framework\Traitables\MacropaySolutionsKernelConsoleMacroCacheCommand;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'macro:cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compile dynamic macros as native PHP traits for production';

    protected Filesystem $files;

    /**
     * Create a new macro cache command instance.
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
        $this->callSilent('macro:clear');

        $cacheDir = $this->app->bootstrapPath('cache' . DIRECTORY_SEPARATOR . 'traitables');

        $this->files->ensureDirectoryExists($cacheDir);

        $macroableInterface = \MacropaySolutions\Kernel\Macroable\Contracts\Macroable::class;
        $classMap = require $this->app->basePath('vendor/composer/autoload_classmap.php');

        $count = 0;
        $skipped = 0;

        foreach (\array_keys(\array_diff_key($classMap, [$macroableInterface => true])) as $class) {
            if (
                !\str_starts_with($class, 'MacropaySolutions\\Kernel\\')
                && !\str_starts_with($class, 'MacropaySolutions\\Framework\\')
            ) {
                continue;
            }

            try {
                if (!\is_subclass_of($class, $macroableInterface)) {
                    continue;
                }
            } catch (\Throwable) {
                continue;
            }

            try {
                $reflector = new \ReflectionClass($class);
            } catch (\ReflectionException $e) {
                $this->warn("Failed to reflect class {$class}: {$e->getMessage()}");
                $skipped++;

                continue;
            }

            if ($reflector->isInterface() || $reflector->isTrait()) {
                continue;
            }

            if (!$reflector->hasProperty('macros')) {
                continue;
            }

            try {
                $macros = $reflector->getStaticPropertyValue('macros', []);
            } catch (\ReflectionException $e) {
                $this->warn("Cannot access macros property on {$class}: {$e->getMessage()}");
                $skipped++;

                continue;
            } catch (\Throwable $e) {
                $this->error("Unexpected error accessing macros on {$class}: {$e->getMessage()}");
                $skipped++;

                continue;
            }

            if ([] === $macros) {
                continue;
            }

            try {
                $this->compileTrait($class, $macros, $cacheDir);

                $count++;
            } catch (\Throwable $e) {
                $this->error("Failed to compile trait for {$class}: {$e->getMessage()}");
                $skipped++;
            }
        }

        $this->info("Macro traits compiled successfully for {$count} classes.");

        if ($skipped > 0) {
            $this->warn("Skipped {$skipped} classes due to errors.");
        }
    }

    /**
     * @throws \Exception
     */
    protected function compileTrait(string $class, array $macros, string $cacheDir): void
    {
        $shortTraitName = \str_replace('\\', '', $class);
        $methods = [];

        foreach ($macros as $name => $factory) {
            if (!\is_string($name)) {
                throw new \Exception("Macro name must be a string in {$class}");
            }

            if (!\preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
                throw new \Exception(
                    "Invalid macro name '{$name}' in {$class}: must match /^[A-Za-z_][A-Za-z0-9_]*$/"
                );
            }

            if (!\is_array($factory) || !\is_string($factory[0]) || !\is_callable($factory)) {
                throw new \Exception("Macro '{$name}' in {$class} has non-callable value");
            }


            try {
                $factoryExport = \var_export($factory, true);
            } catch (\Throwable $e) {
                throw new \Exception("Cannot export factory for macro '{$name}' in {$class}: {$e->getMessage()}");
            }

            try {
                $closure = $factory();

                if (!$closure instanceof \Closure) {
                    throw new \Exception("Macro '{$name}' in {$class} factory did not return a Closure");
                }

                $rf = new \ReflectionFunction($closure);

                $isStatic = $rf->isStatic();

                $extracted = $this->extractSignature($rf);

                $signature = $extracted['signature'];
                $callArgs = $extracted['callArgs'];
                $returnType = $extracted['returnType'];
                $returnsReference = $extracted['returnsReference'];
            } catch (\ReflectionException $e) {
                throw new \Exception("Cannot extract signature for macro '{$name}' in " .
                    "{$class}: {$e->getMessage()}.");
            } catch (\Throwable $e) {
                throw new \Exception("Unexpected error processing macro '{$name}' in {$class}: " .
                    "{$e->getMessage()}.");
            }

            $reference = $returnsReference ? '&' : '';
            $boundCallArgs = $callArgs === '' ? '$this' : '$this, ' . $callArgs;

            if ($isStatic) {
                $methods[] = <<<PHP
    public static function {$reference}{$name}({$signature}){$returnType}
    {
        return ({$factoryExport})()({$callArgs});
    }
PHP;

                continue;
            }

            $methods[] = <<<PHP
    public function {$reference}{$name}({$signature}){$returnType}
    {
        return ({$factoryExport})()->call({$boundCallArgs});
    }
PHP;
        }

        if ([] === $methods) {
            throw new \Exception("No valid macros found for {$class}");
        }

        $methodsCode = \implode("\n\n", $methods);

        $content = <<<PHP
<?php

namespace MacropaySolutions\Framework\Traitables;

trait {$shortTraitName}
{
    use \MacropaySolutions\Kernel\Support\Traits\CompiledMacroable;

{$methodsCode}
}

PHP;

        $traitPath = \implode(DIRECTORY_SEPARATOR, [$cacheDir, $shortTraitName . '.php']);

        try {
            $this->files->put($traitPath, $content);
        } catch (\Throwable $e) {
            throw new \RuntimeException("Failed to write trait file {$traitPath}: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * @return array{
     *     signature: string,
     *     callArgs: string,
     *     returnType: string,
     *     returnsReference: bool
     * }
     *
     * @throws \ReflectionException
     */
    protected function extractSignature(\ReflectionFunction $rf): array
    {
        $params = [];
        $args = [];
        $scope = $rf->getClosureScopeClass();

        foreach ($rf->getParameters() as $param) {
            $declaration = '';

            if ($param->hasType()) {
                $declaration .= $this->formatType($param->getType(), $scope) . ' ';
            }

            if ($param->isPassedByReference()) {
                $declaration .= '&';
            }

            if ($param->isVariadic()) {
                $declaration .= '...';
            }

            $declaration .= '$' . $param->getName();

            if ($param->isDefaultValueAvailable()) {
                $declaration .= ' = ' . $this->formatDefaultValue($param);
            }

            $params[] = $declaration;

            $args[] = ($param->isVariadic() ? '...' : '') . '$' . $param->getName();
        }

        $returnType = '';

        if ($rf->hasReturnType()) {
            $returnType = ': ' . $this->formatType($rf->getReturnType(), $scope);
        }

        return [
            'signature' => \implode(', ', $params),
            'callArgs' => \implode(', ', $args),
            'returnType' => $returnType,
            'returnsReference' => $rf->returnsReference(),
        ];
    }

    /**
     * Format a reflection type as valid PHP source.
     */
    protected function formatType(
        \ReflectionType $type,
        ?\ReflectionClass $scope = null,
        bool $insideUnion = false,
    ): string {
        if ($type instanceof \ReflectionNamedType) {
            $name = $type->getName();

            $parent = $scope?->getParentClass();

            $name = match (true) {
                $name === 'self' && $scope !== null => '\\' . \ltrim($scope->getName(), '\\'),
                $name === 'parent' && $parent instanceof \ReflectionClass => '\\' .
                    \ltrim($parent->getName(), '\\'),
                !$this->isBuiltinType($name) => '\\' . \ltrim($name, '\\'),
                default => $name,
            };

            if (
                $type->allowsNull()
                && $name !== 'mixed'
                && $name !== 'null'
            ) {
                return '?' . $name;
            }

            return $name;
        }

        if ($type instanceof \ReflectionUnionType) {
            return \implode('|', \array_map(
                fn(\ReflectionType $type): string => $this->formatType($type, $scope, true),
                $type->getTypes(),
            ));
        }

        if ($type instanceof \ReflectionIntersectionType) {
            $formatted = \implode('&', \array_map(
                fn(\ReflectionType $type): string => $this->formatType($type, $scope),
                $type->getTypes(),
            ));

            return $insideUnion ? '(' . $formatted . ')' : $formatted;
        }

        return '';
    }

    /**
     * Determine whether a reflected type is a built-in PHP type.
     */
    protected function isBuiltinType(string $type): bool
    {
        return \in_array($type, [
            'array',
            'bool',
            'callable',
            'false',
            'float',
            'int',
            'iterable',
            'mixed',
            'never',
            'null',
            'object',
            'self',
            'static',
            'string',
            'true',
            'void',
        ], true);
    }

    /**
     * Format a reflected parameter default value as PHP source.
     *
     * @throws \ReflectionException
     */
    protected function formatDefaultValue(\ReflectionParameter $param): string
    {
        $value = $param->getDefaultValue();

        if ($value === null) {
            return 'null';
        }

        return \str_replace("\n", '', \var_export($value, true));
    }
}
