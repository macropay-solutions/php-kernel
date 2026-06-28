<?php

namespace MacropaySolutions\Kernel\Database\Obvious;

use OutOfBoundsException;

class MissingAttributeException extends OutOfBoundsException
{
    /**
     * Create a new missing attribute exception instance.
     *
     * @param \MacropaySolutions\Kernel\Database\Obvious\Model $model
     * @param string $key
     * @return void
     */
    public function __construct($model, $key)
    {
        parent::__construct(
            sprintf(
                'The attribute [%s] either does not exist or was not retrieved for model [%s].',
                $key,
                get_class($model)
            )
        );
    }
}
