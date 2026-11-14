<?php

namespace MacropaySolutions\Kernel\View\Compilers\Concerns;

trait CompilesJson
{
    /**
     * The default JSON encoding options.
     *
     * @var int
     */
    private $encodingOptions = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;

    /**
     * Compile the JSON statement into valid PHP.
     *
     * @param string $expression
     * @return string
     */
    protected function compileJson($expression)
    {
        // PHP splits the arguments: splitting on ',' broke values that contain a comma (an array, a call
        // with arguments) and silently dropped the default HTML-safe encoding options
        return '<?php echo \\json_encode(...\\array_replace([null, ' . $this->encodingOptions . ', 512], ['
            . $this->stripParentheses($expression) . '])) ?>';
    }
}
