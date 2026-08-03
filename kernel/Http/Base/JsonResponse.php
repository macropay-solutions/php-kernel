<?php

namespace MacropaySolutions\Kernel\Http\Base;

use MacropaySolutions\Kernel\Http\ResponseTrait;
use MacropaySolutions\Kernel\Support\Traits\Macroable;
use Symfony\Component\HttpFoundation\JsonResponse as BaseJsonResponse;

/**
 * Use this class instead of its parent in your code base!!!
 */
class JsonResponse extends BaseJsonResponse
{
    use ResponseTrait;
    use Macroable {
        Macroable::__call as macroCall;
    }
}
