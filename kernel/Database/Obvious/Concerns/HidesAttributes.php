<?php

namespace MacropaySolutions\Kernel\Database\Obvious\Concerns;

trait HidesAttributes
{
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<string>
     */
    protected array $hidden = [];

    /**
     * The attributes that should be visible in serialization.
     *
     * @var array<string>
     */
    protected array $visible = [];

    /**
     * Get the hidden attributes for the model.
     *
     * @return array<string>
     */
    public function getHidden(): array
    {
        return $this->hidden;
    }

    /**
     * Set the hidden attributes for the model.
     *
     * @param array<string> $hidden
     */
    public function setHidden(array $hidden): static
    {
        $this->hidden = $hidden;

        return $this;
    }

    /**
     * Get the visible attributes for the model.
     *
     * @return array<string>
     */
    public function getVisible(): array
    {
        return $this->visible;
    }

    /**
     * Set the visible attributes for the model.
     *
     * @param array<string> $visible
     */
    public function setVisible(array $visible): static
    {
        $this->visible = $visible;

        return $this;
    }

    /**
     * Make the given, typically hidden, attributes visible.
     *
     * @param array<string>|string|null $attributes
     */
    public function makeVisible(array|string|null $attributes): static
    {
        $attributes = \is_array($attributes) ? $attributes : func_get_args();

        $this->hidden = \array_diff($this->hidden, $attributes);

        if ([] !== ($this->visible)) {
            $this->visible = \array_values(\array_unique(\array_merge($this->visible, $attributes)));
        }

        return $this;
    }

    /**
     * Make the given, typically hidden, attributes visible if the given truth test passes.
     *
     * @param array<string>|string|null $attributes
     */
    public function makeVisibleIf(bool|\Closure $condition, array|string|null $attributes): static
    {
        return value($condition, $this) ? $this->makeVisible($attributes) : $this;
    }

    /**
     * Make the given, typically visible, attributes hidden.
     *
     * @param array<string>|string|null $attributes
     */
    public function makeHidden(array|string|null $attributes): static
    {
        $this->hidden = \array_values(
            \array_unique(
                \array_merge(
                    $this->hidden,
                    \is_array($attributes) ? $attributes : \func_get_args()
                )
            )
        );

        return $this;
    }

    /**
     * Make the given, typically visible, attributes hidden if the given truth test passes.
     *
     * @param bool|\Closure $condition
     * @param array<string>|string|null $attributes
     * @return $this
     */
    public function makeHiddenIf(bool|\Closure $condition, array|string|null $attributes): static
    {
        return value($condition, $this) ? $this->makeHidden($attributes) : $this;
    }
}
