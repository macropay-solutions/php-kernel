<?php

namespace MacropaySolutions\Kernel\Tests\Auth;

use MacropaySolutions\Kernel\Auth\Recaller;
use PHPUnit\Framework\TestCase;

/**
 * A "remember me" cookie sent as "name[x]=y" arrives as an array; it must be an invalid recaller,
 * not a TypeError from unserialize().
 */
class RecallerNonStringTest extends TestCase
{
    public function testArrayRecallerIsInvalid(): void
    {
        $this->assertFalse((new Recaller(['x' => null]))->valid());
    }

    public function testNullRecallerIsInvalid(): void
    {
        $this->assertFalse((new Recaller(null))->valid());
    }

    public function testStringRecallerIsValid(): void
    {
        $recaller = new Recaller('5|token|hash');

        $this->assertTrue($recaller->valid());
        $this->assertSame('5', $recaller->id());
        $this->assertSame('token', $recaller->token());
    }
}
