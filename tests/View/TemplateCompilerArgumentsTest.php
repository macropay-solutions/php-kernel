<?php

namespace MacropaySolutions\Kernel\Tests\View;

use MacropaySolutions\Kernel\Filesystem\Filesystem;
use MacropaySolutions\Kernel\View\Compilers\TemplateCompiler;
use PHPUnit\Framework\TestCase;

/**
 * Directive arguments were split on every ',': @json lost its HTML-safe encoding options (or produced invalid PHP)
 * for values containing a comma, and @pushIf broke on a condition with arguments.
 */
class TemplateCompilerArgumentsTest extends TestCase
{
    private TemplateCompiler $compiler;

    protected function setUp(): void
    {
        $this->compiler = new TemplateCompiler(new Filesystem(), \sys_get_temp_dir());
    }

    public function testJsonOfAnArrayKeepsTheHtmlSafeOptions(): void
    {
        $this->assertSame(
            '{"a":"\u003Cb\u003E","b":"x\u0022 onmouseover=alert(1)"}',
            $this->render("@json(['a' => '<b>', 'b' => 'x\" onmouseover=alert(1)'])")
        );
    }

    public function testJsonOfAnArrayWithManyElementsCompiles(): void
    {
        $this->assertSame(
            '{"a":1,"b":2,"c":3,"d":4}',
            $this->render("@json(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4])")
        );
    }

    public function testJsonWithExplicitOptions(): void
    {
        $this->assertSame("{\n    \"q\": 1\n}", $this->render('@json(["q" => 1], JSON_PRETTY_PRINT)'));
    }

    public function testPushIfWithAConditionThatHasArguments(): void
    {
        $this->assertSame(
            "<?php if(in_array(\$a, ['x', 'y'])): \$__env->startPush('scripts'); ?>",
            $this->compiler->compileString("@pushIf(in_array(\$a, ['x', 'y']), 'scripts')")
        );
    }

    public function testElsePushIfWithAConditionThatHasArguments(): void
    {
        $this->assertSame(
            "<?php \$__env->stopPush(); elseif(in_array(\$a, ['x', 'y'])): \$__env->startPush('other'); ?>",
            $this->compiler->compileString("@elsePushIf(in_array(\$a, ['x', 'y']), 'other')")
        );
    }

    private function render(string $template): string
    {
        \ob_start();
        eval('?>' . $this->compiler->compileString($template));

        return (string)\ob_get_clean();
    }
}
