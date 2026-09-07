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
    protected $description = 'Compile dynamic macros into native PHP traits for production';

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

        $cacheDir = $this->app->bootstrapPath('cache/traitables');
        $this->files->ensureDirectoryExists($cacheDir);

        $macroableInterface = \MacropaySolutions\Kernel\Macroable\Contracts\Macroable::class;
        $classMap = require $this->app->basePath('vendor/composer/autoload_classmap.php');

        $count = 0;

        foreach (\array_keys(\array_diff_key($classMap, [$macroableInterface => true])) as $class) {
            if (!\is_subclass_of($class, $macroableInterface)) {
                continue;
            }

            try {
                $reflector = new \ReflectionClass($class);
            } catch (\ReflectionException) {
                continue;
            }

            if ($reflector->isInterface() || $reflector->isTrait()) {
                continue;
            }

            if (!$reflector->hasProperty('macros')) {
                continue;
            }

            $macros = $reflector->getStaticPropertyValue('macros', []);

            if ([] === $macros) {
                continue;
            }

            $this->compileTrait($class, $macros, $cacheDir);
            $count++;
        }

        $this->components->info('Macro traits compiled and cached successfully for ' . $count . ' classes.');
    }

    protected function compileTrait(string $class, array $macros, string $cacheDir): void
    {
        $shortTraitName = \str_replace('\\', '', $class);
        $methods = [];

        foreach ($macros as $name => $macro) {
            if (!\is_array($macro) || !isset($macro['c'])) {
                continue;
            }

            $factory = $macro['c'];
            $factoryExport = \var_export($factory, true);
            $isStatic = false;

            try {
                $closure = $factory();

                if ($closure instanceof \Closure) {
                    $rf = new \ReflectionFunction($closure);
                    $isStatic = $rf->isStatic();
                }
            } catch (\Throwable) {
                // Default to instance method if closure resolution fails at compile time
            }

            if ($isStatic) {
                $methods[] = <<<PHP
    public static function {$name}(...\$parameters)
    {
        return ({$factoryExport})()(...\$parameters);
    }
PHP;
            } else {
                $methods[] = <<<PHP
    public function {$name}(...\$parameters)
    {
        return ({$factoryExport})()->call(\$this, ...\$parameters);
    }
PHP;
            }
        }

        if (empty($methods)) {
            return;
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

        $this->files->put($cacheDir . DIRECTORY_SEPARATOR . $shortTraitName . '.php', $content);
    }
}
