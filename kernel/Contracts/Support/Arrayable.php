<?php

namespace MacropaySolutions\Kernel\Contracts\Support;

/**
 * @template TKey of array-key
 * @template TValue
 */
interface Arrayable
{
    /**
     * Get the instance as an array.
     */
    public function toArray(): array;
}
