<?php

namespace MacropaySolutions\Kernel\Http\Base;

use MacropaySolutions\Kernel\Http\ResponseTrait;
use MacropaySolutions\Kernel\Support\Traits\Macroable;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Use this class instead of its parent in your code base!!!
 */
class Response extends SymfonyResponse
{
    use ResponseTrait;
    use Macroable {
        Macroable::__call as macroCall;
    }
}
