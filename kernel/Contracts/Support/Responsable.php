<?php

namespace MacropaySolutions\Kernel\Contracts\Support;

interface Responsable
{
    /**
     * Create an HTTP response that represents the object.
     *
     * @param \MacropaySolutions\Kernel\Http\Request $request
     * @return \MacropaySolutions\Kernel\Http\Base\Response
     */
    public function toResponse($request);
}
