<?php

namespace MacropaySolutions\Kernel\Tests\View;

use MacropaySolutions\Kernel\Filesystem\Filesystem;
use MacropaySolutions\Kernel\View\Compilers\TemplateCompiler;
use PHPUnit\Framework\TestCase;

/**
 * An inline @php(...) followed later by a @php ... @endphp block was taken as the start of that block: everything in
 * between was compiled as raw PHP.
 */
class TemplatePhpBlocksTest extends TestCase
{
    public function testInlinePhpBeforeABlock(): void
    {
        $compiler = new TemplateCompiler(new Filesystem(), \sys_get_temp_dir());

        $this->assertSame(
            "<?php (\$a = 1); ?>\n<p><?php echo e(\$a); ?></p>\n<?php\n\$b = 2;\n?>",
            $compiler->compileString("@php(\$a = 1)\n<p>{{ \$a }}</p>\n@php\n\$b = 2;\n@endphp")
        );
        $this->assertSame(
            "<?php (\$y = 2); ?>\n<?php \$z = 3; ?>",
            $compiler->compileString("@php (\$y = 2)\n@php \$z = 3; @endphp")
        );
    }
}
