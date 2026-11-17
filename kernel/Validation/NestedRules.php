<?php

namespace MacropaySolutions\Kernel\Validation;

use MacropaySolutions\Kernel\Support\Arr;

class NestedRules
{
    /**
     * The callback to execute.
     *
     * @var callable
     */
    protected $callback;

    private ?array $undotSource = null;

    private array $undotted = [];

    /**
     * Create a new nested rule instance.
     *
     * @param callable $callback
     * @return void
     */
    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    /**
     * Compile the callback into an array of rules.
     *
     * @param string $attribute
     * @param mixed $value
     * @param mixed $data
     * @return \stdClass
     */
    public function compile($attribute, $value, $data = null)
    {
        $rules = call_user_func($this->callback, $value, $attribute, $data);

        $wrapped = Arr::wrap($data);

        // the same data comes for every item of a wildcard: undotting it each time made large arrays quadratic
        if ($this->undotSource !== $wrapped) {
            $this->undotSource = $wrapped;
            $this->undotted = Arr::undot($wrapped);
        }

        $parser = new ValidationRuleParser($this->undotted);

        if (is_array($rules) && !array_is_list($rules)) {
            $nested = [];

            foreach ($rules as $key => $rule) {
                $nested[$attribute . '.' . $key] = $rule;
            }

            $rules = $nested;
        } else {
            $rules = [$attribute => $rules];
        }

        return $parser->explode(ValidationRuleParser::filterConditionalRules($rules, $data));
    }
}
