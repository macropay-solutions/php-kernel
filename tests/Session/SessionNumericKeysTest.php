<?php

namespace MacropaySolutions\Kernel\Tests\Session;

use MacropaySolutions\Kernel\Session\ArraySessionHandler;
use MacropaySolutions\Kernel\Session\Store;
use PHPUnit\Framework\TestCase;

/**
 * Loading a session merged the stored values with array_merge, which renumbered numeric keys.
 */
class SessionNumericKeysTest extends TestCase
{
    public function testNumericKeysSurviveASaveAndLoad(): void
    {
        $handler = new ArraySessionHandler(10);
        $id = \str_repeat('a', 40);

        $session = new Store('test', $handler, $id);
        $session->start();
        $session->put('123', 'order');
        $session->put('name', 'ana');
        $session->save();

        $loaded = new Store('test', $handler, $id);
        $loaded->start();

        $this->assertSame('order', $loaded->get('123'));
        $this->assertSame('ana', $loaded->get('name'));
    }
}
