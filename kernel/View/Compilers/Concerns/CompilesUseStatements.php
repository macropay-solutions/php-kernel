<?php

namespace MacropaySolutions\Kernel\View\Compilers\Concerns;

trait CompilesUseStatements
{
    /**
     * Compile the use statements into valid PHP.
     *
     * @param string $expression
     * @return string
     */
    protected function compileUse($expression)
    {
        $expression = \preg_replace('/[()]/', '', $expression);

        // a group import keeps its commas: {User, Post} became "User as Post"
        if (\str_contains($expression, '{')) {
            return '<?php use \\' . \ltrim(\trim($expression, " '\""), '\\') . '; ?>';
        }

        $segments = \explode(',', $expression);

        $use = ltrim(trim($segments[0], " '\""), '\\');
        $as = isset($segments[1]) ? ' as ' . trim($segments[1], " '\"") : '';

        return "<?php use \\{$use}{$as}; ?>";
    }
}
