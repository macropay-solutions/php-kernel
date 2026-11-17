<?php

namespace MacropaySolutions\Kernel\Tests\Support;

use MacropaySolutions\Kernel\Http\Middleware\TrimStrings;
use MacropaySolutions\Kernel\Support\HtmlString;
use MacropaySolutions\Kernel\Support\Js;
use MacropaySolutions\Kernel\Support\Number;
use MacropaySolutions\Kernel\Support\Str;
use PHPUnit\Framework\TestCase;

class StrNumberJsEdgeCasesTest extends TestCase
{
    public function testSubstrReplaceCountsCharacters(): void
    {
        $this->assertSame('ăXșț', Str::substrReplace('ăîșț', 'X', 1, 1));
        $this->assertSame('ăXț', Str::substrReplace('ăîșț', 'X', 1, -1));
        $this->assertSame('12:00', Str::substrReplace('1200', ':', 2, 0));
    }

    public function testWordWrapCountsCharacters(): void
    {
        $this->assertSame("ăăăă\năăăă", Str::wordWrap('ăăăă ăăăă', 4, "\n", true));
        $this->assertSame("Țară\nfrumoasă\nși mare", Str::wordWrap('Țară frumoasă și mare', 10));
    }

    public function testNullHaystackIsFalse(): void
    {
        $this->assertFalse(Str::startsWith(null, 'a'));
        $this->assertFalse(Str::endsWith(null, 'a'));
        $this->assertFalse(Str::contains(null, 'a'));
    }

    public function testLongWhitespaceRunsAreTrimmed(): void
    {
        $value = 'a' . \str_repeat(' ', 20000) . 'b';

        $this->assertSame('a b', Str::squish($value));

        $middleware = (new \ReflectionClass(TrimStrings::class))->newInstanceWithoutConstructor();
        $transform = new \ReflectionMethod($middleware, 'transform');

        $this->assertSame('a', $transform->invoke($middleware, 'key', "\u{200B} a" . \str_repeat(' ', 20000)));
    }

    public function testSummariesOfNonFiniteAndSmallNumbers(): void
    {
        $this->assertSame('∞', Number::forHumans(INF));
        $this->assertSame('0', Number::forHumans(-0.001));
        $this->assertSame('0.00', Number::forHumans(0.001, 2));
        $this->assertSame('0', Number::abbreviate(-0.0001));
    }

    public function testSummariesRollOverToTheNextUnit(): void
    {
        $this->assertSame('1M', Number::abbreviate(999999));
        $this->assertSame('1 million', Number::forHumans(999999));
        $this->assertSame('999.9K', Number::abbreviate(999949, 1));
    }

    public function testNoNegativeZero(): void
    {
        $this->assertSame('0', Number::format(-0.001, 0));
        $this->assertSame('0%', Number::percentage(-0.001));
        $this->assertSame('$0.00', Number::currency(-0.001));
        $this->assertSame('-$5.00', Number::currency(-5));
    }

    public function testFileSizeOfNegativeAndNonFiniteBytes(): void
    {
        $this->assertSame('-1 KB', Number::fileSize(-1024));
        $this->assertSame('∞ B', Number::fileSize(INF));
    }

    public function testJsRendersHtmlableAndInvalidUtf8(): void
    {
        $this->assertSame("'\\u003Cb\\u003Ex'", (string)Js::from(new HtmlString('<b>x')));
        $this->assertTrue(\str_contains((string)Js::from(['n' => "a\xffb"]), 'ufffd'));
    }
}
