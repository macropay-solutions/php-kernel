<?php

namespace MacropaySolutions\Kernel\Tests\Translation;

use MacropaySolutions\Kernel\Translation\ArrayLoader;
use MacropaySolutions\Kernel\Translation\Translator;
use PHPUnit\Framework\TestCase;

/**
 * A line missing in the current locale was taken from the fallback locale but pluralized with the current locale's
 * rules (Romanian has three forms, English two).
 */
class TransChoiceFallbackLocaleTest extends TestCase
{
    public function testAFallbackLineUsesTheFallbackRules(): void
    {
        $loader = new ArrayLoader();
        $loader->addMessages('en', 'fruit', ['apples' => 'one apple|many apples']);
        $loader->addMessages('ro', 'fruit', ['pears' => 'o pară|câteva pere|multe pere']);

        $translator = new Translator($loader, 'ro');
        $translator->setFallback('en');

        $this->assertSame('many apples', $translator->choice('fruit.apples', 20));
        $this->assertSame('one apple', $translator->choice('fruit.apples', 1));
        $this->assertSame('multe pere', $translator->choice('fruit.pears', 20));
        $this->assertSame('câteva pere', $translator->choice('fruit.pears', 2));
    }
}
