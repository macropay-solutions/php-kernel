<?php

namespace MacropaySolutions\Kernel\Tests\Support;

use MacropaySolutions\Kernel\Support\MessageBag;
use PHPUnit\Framework\TestCase;

/**
 * MessageBag::add() skips duplicates; numeric strings that only compare equal loosely are different messages.
 */
class MessageBagNumericStringTest extends TestCase
{
    public function testDistinctNumericStringsAreKept(): void
    {
        $bag = new MessageBag();
        $bag->add('code', '1');
        $bag->add('code', '01');
        $bag->add('code', '1.0');
        $bag->add('code', '1');

        $this->assertSame(['1', '01', '1.0'], $bag->get('code'));
    }
}
