<?php

namespace MacropaySolutions\Kernel\Database\Obvious;

use MacropaySolutions\Kernel\Contracts\Queue\EntityNotFoundException;
use MacropaySolutions\Kernel\Contracts\Queue\EntityResolver as EntityResolverContract;

class QueueEntityResolver implements EntityResolverContract
{
    /**
     * Resolve the entity for the given ID.
     *
     * @param string $type
     * @param mixed $id
     * @return mixed
     *
     * @throws \MacropaySolutions\Kernel\Contracts\Queue\EntityNotFoundException
     */
    public function resolve($type, $id)
    {
        $instance = (new $type())->find($id);

        if ($instance) {
            return $instance;
        }

        throw new EntityNotFoundException($type, $id);
    }
}
