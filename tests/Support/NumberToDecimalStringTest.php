<?php

namespace MacropaySolutions\Kernel\Tests\Support;

use MacropaySolutions\Kernel\Support\Number;
use PHPUnit\Framework\TestCase;

/**
 * A small or large float is cast with an exponent ("1.0E-7"), which bcmath refuses and SQL reads as an approximate
 * literal: increment() / decrement() threw or lost precision.
 */
class NumberToDecimalStringTest extends TestCase
{
    public function testExponentsAreExpanded(): void
    {
        $this->assertSame('0.0000001', Number::toDecimalString(1e-7));
        $this->assertSame('0.0000001', Number::toDecimalString('1e-7'));
        $this->assertSame('-0.0015', Number::toDecimalString('-1.5E-3'));
        $this->assertSame('-2500', Number::toDecimalString('-2.5e+3'));
        $this->assertSame('1000000000000000000000', Number::toDecimalString(1e21));
        $this->assertSame('12.5', Number::toDecimalString('0.125e2'));
        $this->assertSame('0.00012345', Number::toDecimalString(1.2345e-4));
    }

    public function testPlainNumbersAreNormalized(): void
    {
        $this->assertSame('5', Number::toDecimalString(5));
        $this->assertSame('-5', Number::toDecimalString(-5));
        $this->assertSame('0.1', Number::toDecimalString(0.1));
        $this->assertSame('10.5', Number::toDecimalString('10.50'));
        $this->assertSame('0.5', Number::toDecimalString('.5'));
        $this->assertSame('5', Number::toDecimalString('+5.'));
        $this->assertSame('5', Number::toDecimalString(' 5 '));
        $this->assertSame('0', Number::toDecimalString('-0.0'));
        $this->assertSame('100', Number::toDecimalString('100'));
        $this->assertSame('0', Number::toDecimalString(0.0));
    }

    public function testNonFiniteNumbersAreRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Number::toDecimalString(\INF);
    }

    public function testHugeExponentsAreRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Number::toDecimalString('1e1001');
    }
}
