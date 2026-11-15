<?php

namespace MacropaySolutions\Kernel\Tests\Validation;

use MacropaySolutions\Kernel\Validation\Rules\Exists;
use MacropaySolutions\Kernel\Validation\Rules\Unique;
use PHPUnit\Framework\TestCase;

/**
 * A false where() / whereNot() value on an exists / unique rule was formatted as an empty string.
 */
class DatabaseRuleFalseValueTest extends TestCase
{
    public function testFalseIsFormattedAsZero(): void
    {
        $this->assertSame('exists:users,NULL,active,"0"', (string)(new Exists('users'))->where('active', false));
        $this->assertSame(
            'unique:users,NULL,NULL,id,active,"!0"',
            (string)(new Unique('users'))->whereNot('active', false)
        );
    }

    public function testTrueIsFormattedAsOne(): void
    {
        $this->assertSame('exists:users,NULL,active,"1"', (string)(new Exists('users'))->where('active', true));
    }
}
