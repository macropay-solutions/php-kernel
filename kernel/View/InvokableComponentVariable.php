<?php

namespace MacropaySolutions\Kernel\View;

use ArrayIterator;
use Closure;
use IteratorAggregate;
use MacropaySolutions\Kernel\Contracts\Support\DeferringDisplayableValue;
use MacropaySolutions\Kernel\Macroable\Traits\ExplicitForwardable;
use MacropaySolutions\Kernel\Support\Enumerable;
use Traversable;

class InvokableComponentVariable implements DeferringDisplayableValue, IteratorAggregate
{
    use ExplicitForwardable;

    /**
     * The callable instance to resolve the variable value.
     *
     * @var \Closure
     */
    protected $callable;

    /**
     * Create a new variable instance.
     *
     * @param \Closure $callable
     * @return void
     */
    public function __construct(Closure $callable)
    {
        $this->callable = $callable;
    }

    /**
     * Resolve the displayable value that the class is deferring.
     *
     * @return \MacropaySolutions\Kernel\Contracts\Support\Htmlable|string
     */
    public function resolveDisplayableValue()
    {
        return $this->__invoke();
    }

    /**
     * Get an iterator instance for the variable.
     *
     * @return \ArrayIterator
     */
    public function getIterator(): Traversable
    {
        $result = $this->__invoke();

        return new ArrayIterator($result instanceof Enumerable ? $result->all() : $result);
    }

    /**
     * Dynamically proxy attribute access to the variable.
     *
     * @param string $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->__invoke()->{$key};
    }

    /**
     * Dynamically proxy method access to the variable.
     */
    public function fwd(): mixed
    {
        return $this->__invoke();
    }

    /**
     * Resolve the variable.
     *
     * @return mixed
     */
    public function __invoke()
    {
        return call_user_func($this->callable);
    }

    /**
     * Resolve the variable as a string.
     *
     * @return mixed
     */
    public function __toString()
    {
        return (string)$this->__invoke();
    }
}
