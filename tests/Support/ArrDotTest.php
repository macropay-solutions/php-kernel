<?php

namespace MacropaySolutions\Kernel\Tests\Support;

use MacropaySolutions\Kernel\Support\Arr;
use MacropaySolutions\Kernel\Validation\ValidationRuleParser;
use PHPUnit\Framework\TestCase;

/**
 * Arr::dot() merged every nested result into the growing array (quadratic), which made validating
 * large arrays take minutes.
 */
class ArrDotTest extends TestCase
{
    public function testDotFlattensAndKeepsKeys(): void
    {
        $this->assertSame(
            [5 => 'a', 'x.y' => 1, 'x.z' => [], '0.0.0' => 1, '0.1.k' => null],
            Arr::dot([5 => 'a', 'x' => ['y' => 1, 'z' => []], 0 => [[1], ['k' => null]]])
        );
        $this->assertSame(['p.a.b' => 1], Arr::dot(['a' => ['b' => 1]], 'p.'));
    }

    public function testLargeArraysAreFlattenedQuickly(): void
    {
        $data = ['items' => \array_fill(0, 20000, ['id' => 1, 'name' => 'x'])];
        $start = \microtime(true);

        $this->assertCount(40000, Arr::dot($data));

        $rules = (new ValidationRuleParser($data))->explode([
            'items.*.id' => 'required|integer',
            'items.*.name' => 'string',
        ]);

        $this->assertCount(40000, $rules->rules);
        $this->assertLessThan(5.0, \microtime(true) - $start);
    }
}
