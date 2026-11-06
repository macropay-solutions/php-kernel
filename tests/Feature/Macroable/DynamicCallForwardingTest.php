<?php

namespace MacropaySolutions\Kernel\Tests\Feature\Macroable;

use MacropaySolutions\Kernel\Macroable\Contracts\Macroable;
use MacropaySolutions\Kernel\Redis\Connections\PredisClusterConnection;
use MacropaySolutions\Kernel\Redis\Connections\PredisConnection;
use MacropaySolutions\Kernel\Tests\Fixtures\Redis\FakeRedisClient;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * Every Macroable child uses its own trait. A trait method beats an inherited method, so when a parent
 * declares its own __call or __callStatic (to forward to a query, a client...), the child has to declare
 * one too, or the trait's __call silently replaces the parent's forwarding.
 */
class DynamicCallForwardingTest extends TestCase
{
    public function test_no_macroable_child_loses_the_dynamic_call_handling_of_its_parent(): void
    {
        $macroableTraitFile = (new \ReflectionClass(\MacropaySolutions\Kernel\Support\Traits\Macroable::class))
            ->getFileName();
        $shadowed = [];

        foreach ($this->macroableClasses() as $class) {
            $reflection = new \ReflectionClass($class);

            foreach (['__call', '__callStatic'] as $method) {
                if (!$reflection->hasMethod($method)) {
                    continue;
                }

                if ($reflection->getMethod($method)->getFileName() !== $macroableTraitFile) {
                    continue;
                }

                for ($parent = $reflection->getParentClass(); $parent !== false; $parent = $parent->getParentClass()) {
                    if (!$parent->hasMethod($method)) {
                        break;
                    }

                    if ($parent->getMethod($method)->getFileName() === $parent->getFileName()) {
                        $shadowed[] = $class . '::' . $method . ' replaces ' . $parent->getName() . '::' . $method;

                        break;
                    }
                }
            }
        }

        $this->assertSame([], $shadowed);
    }

    public static function predisConnections(): array
    {
        return [
            'predis' => [PredisConnection::class],
            'predis cluster' => [PredisClusterConnection::class],
        ];
    }

    #[DataProvider('predisConnections')]
    public function test_predis_connections_pass_unknown_methods_to_the_client(string $connectionClass): void
    {
        $client = new FakeRedisClient();
        $connection = new $connectionClass($client);

        $this->assertTrue($connection->set('name', 'Ana'));
        $this->assertSame('value-of-name', $connection->get('name'));
        $this->assertSame([['set', ['name', 'Ana']], ['get', ['name']]], $client->commands);
    }

    /**
     * @return string[]
     */
    private function macroableClasses(): array
    {
        $root = \dirname(__DIR__, 3);
        $classes = [];

        foreach ((new Finder())->files()->in([$root . '/kernel', $root . '/src'])->name('*.php') as $file) {
            $content = \php_strip_whitespace($file->getRealPath());

            if (
                \preg_match('/namespace\s+([^;]+);/', $content, $namespace) !== 1
                || \preg_match('/(?:^|\s)(?:abstract\s+|final\s+|readonly\s+)*class\s+(\w+)/', $content, $class) !== 1
            ) {
                continue;
            }

            $name = \trim($namespace[1]) . '\\' . $class[1];

            try {
                if (!\class_exists($name) || !\is_subclass_of($name, Macroable::class)) {
                    continue;
                }
            } catch (\Throwable) {
                // Classes of optional packages that are not installed.
                continue;
            }

            $classes[] = $name;
        }

        return $classes;
    }
}
