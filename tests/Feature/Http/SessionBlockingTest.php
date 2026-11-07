<?php

namespace MacropaySolutions\Kernel\Tests\Feature\Http;

use MacropaySolutions\Framework\Http\Request;
use MacropaySolutions\Kernel\Cookie\Middleware\EncryptCookies;
use MacropaySolutions\Kernel\Session\Middleware\StartSession;
use MacropaySolutions\Kernel\Tests\ApplicationTestCase;
use MacropaySolutions\Kernel\Tests\Fixtures\Http\SessionTestController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * With session.block on, a request waits for the session's lock, so two requests of one session never run at
 * the same time. A second request is simulated by holding the lock while the request comes in.
 */
class SessionBlockingTest extends ApplicationTestCase
{
    /**
     * @var array<string, string>
     */
    private array $cookies = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->configure('session');
        $config = $this->app->make('config');
        $config->set('session.driver', 'array');
        $config->set('session.block', true);
        $config->set('session.block_store', 'array');
        $config->set('session.block_wait_seconds', 0);
        $this->app->middleware([EncryptCookies::class, StartSession::class]);

        $this->app->router->post('/cart', SessionTestController::class . '@put');
        $this->app->router->get('/cart', SessionTestController::class . '@get');
    }

    public function test_a_request_runs_when_its_session_is_free(): void
    {
        $this->assertSame(200, $this->send('POST', '/cart', ['item' => 'book'])->getStatusCode());
        $this->assertSame('{"cart":"book","status":"added"}', $this->send('GET', '/cart')->getContent());
    }

    public function test_a_request_does_not_run_while_another_holds_its_session_lock(): void
    {
        $this->send('POST', '/cart', ['item' => 'book']);
        $lock = $this->sessionLock();

        $this->assertTrue($lock->get());

        $blocked = $this->send('GET', '/cart');

        $this->assertSame(500, $blocked->getStatusCode());
        $this->assertStringNotContainsString('"cart":"book"', (string)$blocked->getContent());

        $lock->release();

        $this->assertSame('{"cart":"book","status":"added"}', $this->send('GET', '/cart')->getContent());
    }

    public function test_a_blocked_request_does_not_release_the_lock_it_never_got(): void
    {
        $this->send('POST', '/cart', ['item' => 'book']);
        $lock = $this->sessionLock();
        $lock->get();

        $this->send('GET', '/cart');

        $this->assertFalse($this->sessionLock()->get());

        $lock->release();
    }

    public function test_without_blocking_the_lock_is_ignored(): void
    {
        $this->app->make('config')->set('session.block', false);
        $this->send('POST', '/cart', ['item' => 'book']);
        $lock = $this->sessionLock();
        $lock->get();

        $this->assertSame('{"cart":"book","status":"added"}', $this->send('GET', '/cart')->getContent());

        $lock->release();
    }

    private function sessionLock(): \MacropaySolutions\Kernel\Contracts\Cache\Lock
    {
        $sessionId = $this->app->make('session')->driver()->getId();

        return $this->app->make('cache')->store('array')->fwd()->lock('session:' . $sessionId, 10);
    }

    private function send(string $method, string $uri, array $parameters = []): Response
    {
        $response = $this->app->handle(Request::create($uri, $method, $parameters, $this->cookies));

        foreach ($response->headers->getCookies() as $cookie) {
            /** @var Cookie $cookie */
            $this->cookies[$cookie->getName()] = (string)$cookie->getValue();
        }

        return $response;
    }
}
