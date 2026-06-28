<?php

namespace MacropaySolutions\Kernel\Http\Resources;

use LogicException;
use MacropaySolutions\Kernel\Http\Resources\Json\JsonResource;
use MacropaySolutions\Kernel\Pagination\AbstractCursorPaginator;
use MacropaySolutions\Kernel\Pagination\AbstractPaginator;
use MacropaySolutions\Kernel\Support\Collection;
use MacropaySolutions\Kernel\Support\Str;
use ReflectionClass;
use Traversable;

trait CollectsResources
{
    /**
     * Map the given collection resource into its individual resources.
     *
     * @param mixed $resource
     * @return mixed
     */
    protected function collectResource($resource)
    {
        if ($resource instanceof MissingValue) {
            return $resource;
        }

        if (is_array($resource)) {
//            $resource = new Collection($resource);
            $resource = \di(Collection::class, [$resource]);
        }

        $collects = $this->collects();

        $this->collection = $collects && !$resource->first() instanceof $collects
            ? $resource->mapInto($collects)
            : $resource->toBase();

        return ($resource instanceof AbstractPaginator || $resource instanceof AbstractCursorPaginator)
            ? $resource->setCollection($this->collection)
            : $this->collection;
    }

    /**
     * Get the resource that this resource collects.
     *
     * @return string|null
     */
    protected function collects()
    {
        $collects = null;

        if ($this->collects) {
            $collects = $this->collects;
        } elseif (
            str_ends_with(class_basename($this), 'Collection') &&
            (class_exists($class = Str::replaceLast('Collection', '', static::class)) ||
                class_exists($class = Str::replaceLast('Collection', 'Resource', static::class)))
        ) {
            $collects = $class;
        }

        if (!$collects || is_a($collects, JsonResource::class, true)) {
            return $collects;
        }

        throw new LogicException('Resource collections must collect instances of ' . JsonResource::class . '.');
    }

    /**
     * Get the JSON serialization options that should be applied to the resource response.
     *
     * @return int
     */
    public function jsonOptions()
    {
        $collects = $this->collects();

        if (!$collects) {
            return 0;
        }

        return (new ReflectionClass($collects))
            ->newInstanceWithoutConstructor()
            ->jsonOptions();
    }

    /**
     * Get an iterator for the resource collection.
     *
     * @return \ArrayIterator
     */
    public function getIterator(): Traversable
    {
        return $this->collection->getIterator();
    }
}
