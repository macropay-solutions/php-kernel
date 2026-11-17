<?php

namespace MacropaySolutions\Kernel\Tests\View;

use MacropaySolutions\Kernel\Support\Str;
use MacropaySolutions\Kernel\View\ComponentAttributeBag;
use MacropaySolutions\Kernel\View\Compilers\TemplateCompiler;
use MacropaySolutions\Kernel\View\Factory;
use PHPUnit\Framework\TestCase;

class ComponentAttributesUseGroupAndSlotsTest extends TestCase
{
    private const EVIL = '"><script>alert(1)</script>';

    public function testObjectAndAbsentAppendableDefaultsAreEscaped(): void
    {
        $escaped = '&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;';

        $this->assertSame(
            'title="' . $escaped . '"',
            (string)(new ComponentAttributeBag([]))->merge(['title' => Str::of(self::EVIL)])
        );

        $bag = new ComponentAttributeBag([]);

        $this->assertSame('data-x="' . $escaped . '"', (string)$bag->merge(['data-x' => $bag->prepends(self::EVIL)]));
    }

    public function testUseKeepsAGroupImport(): void
    {
        $compiler = (new \ReflectionClass(TemplateCompiler::class))->newInstanceWithoutConstructor();

        $this->assertSame(
            '<?php use \App\Models\{User, Post}; ?>',
            $compiler->compileString("@use('App\\Models\\{User, Post}')")
        );
        $this->assertSame(
            '<?php use \App\Models\User as U; ?>',
            $compiler->compileString("@use('App\\Models\\User', 'U')")
        );
    }

    public function testFlushStateForgetsTheSlots(): void
    {
        $factory = (new \ReflectionClass(Factory::class))->newInstanceWithoutConstructor();
        $level = \ob_get_level();
        $factory->startComponent('x');
        $factory->slot('secret', 'previous request');

        try {
            (new \ReflectionMethod($factory, 'flushComponents'))->invoke($factory);

            $this->assertSame([], (new \ReflectionProperty($factory, 'slots'))->getValue($factory));
        } finally {
            // startComponent() opened a buffer for the component content
            while (\ob_get_level() > $level) {
                \ob_end_clean();
            }
        }
    }
}
