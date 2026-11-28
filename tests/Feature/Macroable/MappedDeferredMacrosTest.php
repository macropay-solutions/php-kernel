<?php

namespace MacropaySolutions\Kernel\Tests\Feature\Macroable;

use MacropaySolutions\Framework\Application;
use MacropaySolutions\Kernel\Container\Container;
use MacropaySolutions\Kernel\Support\Collection;
use MacropaySolutions\Kernel\Tests\Fixtures\Macros\CollectionMacros;
use PHPUnit\Framework\TestCase;

/**
 * Without the compiled traits, macros mapped in the application's $deferredMacros are looked up on the first call,
 * so nothing is registered while the application boots.
 */
class MappedDeferredMacrosTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        new class (\sys_get_temp_dir()) extends Application {
            protected array $deferredMacros = [
                Collection::class => [
                    'mappedDoubled' => [CollectionMacros::class, 'doubled'],
                    'mappedFromRange' => [CollectionMacros::class, 'fromRange'],
                ],
            ];
        };
    }

    protected function tearDown(): void
    {
        // the application registers its own error and exception handlers
        \restore_error_handler();
        \restore_exception_handler();
        Container::setInstance(null);

        parent::tearDown();
    }

    public function test_mapped_macros_are_called_without_being_registered(): void
    {
        $this->assertTrue(Collection::hasMacro('mappedDoubled'));
        $this->assertSame([3, 6, 9], (new Collection([1, 2, 3]))->mappedDoubled(3)->all());
        $this->assertSame([2, 3, 4], Collection::mappedFromRange(2, 4)->all());
    }

    public function test_a_mapped_macro_cannot_be_registered_again(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Method already exists: mappedDoubled');

        Collection::deferredMacro('mappedDoubled', [CollectionMacros::class, 'doubled']);
    }

    public function test_unmapped_methods_still_throw(): void
    {
        $this->expectException(\BadMethodCallException::class);

        (new Collection([]))->notMapped();
    }
}
