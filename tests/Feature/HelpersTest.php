<?php

namespace MacropaySolutions\Kernel\Tests\Feature;

use MacropaySolutions\Kernel\Tests\Fixtures\Http\TestRequest;
use MacropaySolutions\Kernel\Tests\TestCase;

class HelpersTest extends TestCase
{
    public function test_method_field_escapes_the_method(): void
    {
        $this->assertSame(
            '<input type="hidden" name="_method" value="PUT&quot;&gt;">',
            (string)\method_field('PUT">')
        );
    }

    public function test_old_returns_the_default_without_a_session(): void
    {
        $this->container->instance('request', new TestRequest());

        $this->assertSame('draft', \old('title', 'draft'));
    }
}
