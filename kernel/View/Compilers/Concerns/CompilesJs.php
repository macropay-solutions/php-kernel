<?php

namespace MacropaySolutions\Kernel\View\Compilers\Concerns;

use MacropaySolutions\Kernel\Support\Js;

trait CompilesJs
{
    /**
     * Compile the "@js" directive into valid PHP.
     *
     * @param string $expression
     * @return string
     */
    protected function compileJs(string $expression)
    {
        return sprintf(
            "<?php echo \%s::from(%s)->toHtml() ?>",
            Js::class,
            $this->stripParentheses($expression)
        );
    }
}
