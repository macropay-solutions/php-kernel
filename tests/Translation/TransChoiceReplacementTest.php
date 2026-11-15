<?php

namespace MacropaySolutions\Kernel\Tests\Translation;

use MacropaySolutions\Kernel\Translation\ArrayLoader;
use MacropaySolutions\Kernel\Translation\Translator;
use PHPUnit\Framework\TestCase;

/**
 * choice() replaced the placeholders before choosing the plural form: a "|" in a replaced value split the line.
 */
class TransChoiceReplacementTest extends TestCase
{
    public function testReplacementWithPipeDoesNotSplitTheLine(): void
    {
        $loader = new ArrayLoader();
        $loader->addMessages('en', 'messages', ['apples' => ':name has one apple|:name has :count apples']);
        $translator = new Translator($loader, 'en');

        $this->assertSame('a|b has one apple', $translator->choice('messages.apples', 1, ['name' => 'a|b']));
        $this->assertSame('a|b has 3 apples', $translator->choice('messages.apples', 3, ['name' => 'a|b']));
    }
}
