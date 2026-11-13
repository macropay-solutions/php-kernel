<?php

namespace MacropaySolutions\Kernel\Tests\Session;

use MacropaySolutions\Kernel\Contracts\Cookie\QueueingFactory;
use MacropaySolutions\Kernel\Session\CookieSessionHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * A session cookie sent as "name[x]=y" arrives as an array; read() must return '' instead of a TypeError.
 */
class CookieSessionHandlerMalformedCookieTest extends TestCase
{
    private function read(mixed $cookieValue): string|false
    {
        $handler = new CookieSessionHandler($this->createMock(QueueingFactory::class), 120);
        $handler->setRequest(Request::create('/', 'GET', [], ['session_id' => $cookieValue]));

        return $handler->read('session_id');
    }

    public function testArrayCookieGivesEmptySession(): void
    {
        $this->assertSame('', $this->read(['x' => null]));
    }

    public function testNonJsonCookieGivesEmptySession(): void
    {
        $this->assertSame('', $this->read('not json'));
    }

    public function testNonStringDataGivesEmptySession(): void
    {
        $this->assertSame('', $this->read(\json_encode(['data' => ['a' => 1], 'expires' => \time() + 60])));
    }

    public function testMissingDataGivesEmptySession(): void
    {
        $this->assertSame('', $this->read(\json_encode(['expires' => \time() + 60])));
    }

    public function testExpiredCookieGivesEmptySession(): void
    {
        $this->assertSame('', $this->read(\json_encode(['data' => 'payload', 'expires' => \time() - 60])));
    }

    public function testValidCookieGivesItsData(): void
    {
        $this->assertSame('payload', $this->read(\json_encode(['data' => 'payload', 'expires' => \time() + 60])));
    }
}
