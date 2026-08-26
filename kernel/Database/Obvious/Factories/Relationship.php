<?php

namespace MacropaySolutions\Kernel\Database\Obvious\Factories;

use MacropaySolutions\Kernel\Database\Obvious\Model;
use MacropaySolutions\Kernel\Database\Obvious\Relations\BelongsToMany;
use MacropaySolutions\Kernel\Database\Obvious\Relations\HasOneOrMany;

class Relationship
{
    /**
     * The related factory instance.
     *
     * @var \MacropaySolutions\Kernel\Database\Obvious\Factories\Factory
     */
    protected $factory;

    /**
     * The relationship name.
     *
     * @var string
     */
    protected $relationship;

    /**
     * Create a new child relationship instance.
     *
     * @param \MacropaySolutions\Kernel\Database\Obvious\Factories\Factory $factory
     * @param string $relationship
     * @return void
     */
    public function __construct(Factory $factory, $relationship)
    {
        $this->factory = $factory;
        $this->relationship = $relationship;
    }

    /**
     * Create the child relationship for the given parent model.
     *
     * @param \MacropaySolutions\Kernel\Database\Obvious\Model $parent
     * @return void
     */
    public function createFor(Model $parent)
    {
        $relationship = $parent->{$this->relationship}();

        if ($relationship instanceof HasOneOrMany) {
            $this->factory->state([
                $relationship->getForeignKeyName() => $relationship->getParentKey(),
            ])->create([], $parent);
        } elseif ($relationship instanceof BelongsToMany) {
            $relationship->attach($this->factory->create([], $parent));
        }
    }

    /**
     * Specify the model instances to always use when creating relationships.
     *
     * @param \MacropaySolutions\Kernel\Support\Collection $recycle
     * @return $this
     */
    public function recycle($recycle)
    {
        $this->factory = $this->factory->recycle($recycle);

        return $this;
    }
}
