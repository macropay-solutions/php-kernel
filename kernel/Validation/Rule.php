<?php

namespace MacropaySolutions\Kernel\Validation;

use MacropaySolutions\Kernel\Contracts\Support\Arrayable;
use MacropaySolutions\Kernel\Support\Traits\Macroable;
use MacropaySolutions\Kernel\Validation\Rules\Can;
use MacropaySolutions\Kernel\Validation\Rules\Dimensions;
use MacropaySolutions\Kernel\Validation\Rules\Enum;
use MacropaySolutions\Kernel\Validation\Rules\ExcludeIf;
use MacropaySolutions\Kernel\Validation\Rules\Exists;
use MacropaySolutions\Kernel\Validation\Rules\File;
use MacropaySolutions\Kernel\Validation\Rules\ImageFile;
use MacropaySolutions\Kernel\Validation\Rules\In;
use MacropaySolutions\Kernel\Validation\Rules\NotIn;
use MacropaySolutions\Kernel\Validation\Rules\ProhibitedIf;
use MacropaySolutions\Kernel\Validation\Rules\RequiredIf;
use MacropaySolutions\Kernel\Validation\Rules\Unique;

class Rule
{
    use Macroable;

    /**
     * Get a can constraint builder instance.
     *
     * @param string $ability
     * @param mixed ...$arguments
     * @return \MacropaySolutions\Kernel\Validation\Rules\Can
     */
    public static function can($ability, ...$arguments)
    {
        return new Can($ability, $arguments);
    }

    /**
     * Apply the given rules if the given condition is truthy.
     *
     * @param callable|bool $condition
     * @param \MacropaySolutions\Kernel\Contracts\Validation\ValidationRule|\MacropaySolutions\Kernel\Contracts\Validation\InvokableRule|\MacropaySolutions\Kernel\Contracts\Validation\Rule|\Closure|array|string $rules
     * @param \MacropaySolutions\Kernel\Contracts\Validation\ValidationRule|\MacropaySolutions\Kernel\Contracts\Validation\InvokableRule|\MacropaySolutions\Kernel\Contracts\Validation\Rule|\Closure|array|string $defaultRules
     * @return \MacropaySolutions\Kernel\Validation\ConditionalRules
     */
    public static function when($condition, $rules, $defaultRules = [])
    {
        return new ConditionalRules($condition, $rules, $defaultRules);
    }

    /**
     * Apply the given rules if the given condition is falsy.
     *
     * @param callable|bool $condition
     * @param \MacropaySolutions\Kernel\Contracts\Validation\ValidationRule|\MacropaySolutions\Kernel\Contracts\Validation\InvokableRule|\MacropaySolutions\Kernel\Contracts\Validation\Rule|\Closure|array|string $rules
     * @param \MacropaySolutions\Kernel\Contracts\Validation\ValidationRule|\MacropaySolutions\Kernel\Contracts\Validation\InvokableRule|\MacropaySolutions\Kernel\Contracts\Validation\Rule|\Closure|array|string $defaultRules
     * @return \MacropaySolutions\Kernel\Validation\ConditionalRules
     */
    public static function unless($condition, $rules, $defaultRules = [])
    {
        return new ConditionalRules($condition, $defaultRules, $rules);
    }

    /**
     * Create a new nested rule set.
     *
     * @param callable $callback
     * @return \MacropaySolutions\Kernel\Validation\NestedRules
     */
    public static function forEach($callback)
    {
        return new NestedRules($callback);
    }

    /**
     * Get a unique constraint builder instance.
     *
     * @param string $table
     * @param string $column
     * @return \MacropaySolutions\Kernel\Validation\Rules\Unique
     */
    public static function unique($table, $column = 'NULL')
    {
        return new Unique($table, $column);
    }

    /**
     * Get an exists constraint builder instance.
     *
     * @param string $table
     * @param string $column
     * @return \MacropaySolutions\Kernel\Validation\Rules\Exists
     */
    public static function exists($table, $column = 'NULL')
    {
        return new Exists($table, $column);
    }

    /**
     * Get an in constraint builder instance.
     *
     * @param \MacropaySolutions\Kernel\Contracts\Support\Arrayable|array|string $values
     * @return \MacropaySolutions\Kernel\Validation\Rules\In
     */
    public static function in($values)
    {
        if ($values instanceof Arrayable) {
            $values = $values->toArray();
        }

        return new In(is_array($values) ? $values : func_get_args());
    }

    /**
     * Get a not_in constraint builder instance.
     *
     * @param \MacropaySolutions\Kernel\Contracts\Support\Arrayable|array|string $values
     * @return \MacropaySolutions\Kernel\Validation\Rules\NotIn
     */
    public static function notIn($values)
    {
        if ($values instanceof Arrayable) {
            $values = $values->toArray();
        }

        return new NotIn(is_array($values) ? $values : func_get_args());
    }

    /**
     * Get a required_if constraint builder instance.
     *
     * @param callable|bool $callback
     * @return \MacropaySolutions\Kernel\Validation\Rules\RequiredIf
     */
    public static function requiredIf($callback)
    {
        return new RequiredIf($callback);
    }

    /**
     * Get a exclude_if constraint builder instance.
     *
     * @param callable|bool $callback
     * @return \MacropaySolutions\Kernel\Validation\Rules\ExcludeIf
     */
    public static function excludeIf($callback)
    {
        return new ExcludeIf($callback);
    }

    /**
     * Get a prohibited_if constraint builder instance.
     *
     * @param callable|bool $callback
     * @return \MacropaySolutions\Kernel\Validation\Rules\ProhibitedIf
     */
    public static function prohibitedIf($callback)
    {
        return new ProhibitedIf($callback);
    }

    /**
     * Get an enum constraint builder instance.
     *
     * @param string $type
     * @return \MacropaySolutions\Kernel\Validation\Rules\Enum
     */
    public static function enum($type)
    {
        return new Enum($type);
    }

    /**
     * Get a file constraint builder instance.
     *
     * @return \MacropaySolutions\Kernel\Validation\Rules\File
     */
    public static function file()
    {
//        return new File;
        return \di(File::class);
    }

    /**
     * Get an image file constraint builder instance.
     *
     * @return \MacropaySolutions\Kernel\Validation\Rules\ImageFile
     */
    public static function imageFile()
    {
//        return new ImageFile;
        return \di(ImageFile::class);
    }

    /**
     * Get a dimensions constraint builder instance.
     *
     * @param array $constraints
     * @return \MacropaySolutions\Kernel\Validation\Rules\Dimensions
     */
    public static function dimensions(array $constraints = [])
    {
        return new Dimensions($constraints);
    }
}
