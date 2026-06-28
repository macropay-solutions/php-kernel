<?php

namespace MacropaySolutions\Kernel\Cache;

use MacropaySolutions\Kernel\Contracts\Cache\Store;

abstract class TaggableStore implements Store
{
    /**
     * Begin executing a new tags operation.
     *
     * @param array|mixed $names
     * @return \MacropaySolutions\Kernel\Cache\TaggedCache
     */
    public function tags($names)
    {
//        return new TaggedCache($this, new TagSet($this, is_array($names) ? $names : func_get_args()));
        return \di(TaggedCache::class, [
            $this, \di(TagSet::class, [$this, is_array($names) ? $names : func_get_args()])
        ]);
    }
}
