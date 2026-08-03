<?php

namespace MacropaySolutions\Kernel\Http\Base;

use MacropaySolutions\Kernel\Contracts\Support\Arrayable;
use MacropaySolutions\Kernel\Http\Concerns;
use MacropaySolutions\Kernel\Support\Traits\Macroable;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

/**
 * Use this class instead of its parent in your code base!!!
 */
class Request extends SymfonyRequest implements Arrayable
{
    use Concerns\CanBePrecognitive;
    use Concerns\InteractsWithContentTypes;
    use Concerns\InteractsWithFlashData;
    use Concerns\InteractsWithInput;
    use Macroable;
}
