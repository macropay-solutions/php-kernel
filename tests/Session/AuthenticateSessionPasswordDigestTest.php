<?php

namespace MacropaySolutions\Kernel\Tests\Session;

use MacropaySolutions\Kernel\Auth\AuthenticationException;
use MacropaySolutions\Kernel\Auth\GenericUser;
use MacropaySolutions\Kernel\Config\Repository;
use MacropaySolutions\Kernel\Container\Container;
use MacropaySolutions\Kernel\Contracts\Auth\Factory;
use MacropaySolutions\Kernel\Contracts\Auth\Guard;
use MacropaySolutions\Kernel\Contracts\Auth\StatefulGuard;
use MacropaySolutions\Kernel\Http\Request;
use MacropaySolutions\Kernel\Session\ArraySessionHandler;
use MacropaySolutions\Kernel\Session\Middleware\AuthenticateSession;
use MacropaySolutions\Kernel\Session\Store;
use PHPUnit\Framework\TestCase;

class AuthenticateSessionPasswordDigestTest extends TestCase
{
    private const HASH = '$2y$12$abcdefghijklmnopqrstuuJ8m2nT4e8bQ0b3h1Yy0pX6sVZ3uU8wq';

    protected function setUp(): void
    {
        Container::setInstance($container = new Container());
        $container->instance('config', new Repository(['app' => ['key' => 'base64:test-key']]));
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);
    }

    public function testTheSessionDoesNotHoldThePasswordHash(): void
    {
        [$request, $session] = $this->request(self::HASH);

        (new AuthenticateSession(new AuthenticateSessionPasswordDigestTestAuth()))->handle(
            $request,
            static fn() => 'ok'
        );

        $this->assertSame(\hash_hmac('sha256', self::HASH, 'base64:test-key'), $session->get('password_hash_web'));
        $this->assertFalse(\str_contains(\serialize($session->all()), 'abcdefghijklmnopqrstu'));
    }

    public function testASessionStoredBeforeTheUpgradeIsKeptAndRewritten(): void
    {
        [$request, $session] = $this->request(self::HASH);
        $session->put('password_hash_web', self::HASH);

        $result = (new AuthenticateSession(new AuthenticateSessionPasswordDigestTestAuth()))->handle(
            $request,
            static fn() => 'ok'
        );

        $this->assertSame('ok', $result);
        $this->assertSame(\hash_hmac('sha256', self::HASH, 'base64:test-key'), $session->get('password_hash_web'));
    }

    public function testAChangedPasswordLogsTheSessionOut(): void
    {
        [$request, $session] = $this->request(self::HASH);
        $middleware = new AuthenticateSession($auth = new AuthenticateSessionPasswordDigestTestAuth());
        $middleware->handle($request, static fn() => 'ok');

        [$changed] = $this->request('$2y$12$another-hash', $session);

        try {
            $middleware->handle($changed, static fn() => 'ok');
            $this->fail('expected a logout');
        } catch (AuthenticationException) {
            $this->assertTrue($auth->loggedOut);
        }
    }

    private function request(string $passwordHash, ?Store $session = null): array
    {
        $session ??= new Store('test', new ArraySessionHandler(10), \str_repeat('a', 40));
        $session->start();
        $request = Request::create('/');
        $request->setFrameworkSession($session);
        $user = new GenericUser(['id' => 1, 'password' => $passwordHash]);
        $request->setUserResolver(static fn() => $user);

        return [$request, $session];
    }
}

class AuthenticateSessionPasswordDigestTestAuth implements Factory
{
    public bool $loggedOut = false;

    public function guard($name = null)
    {
        return $this;
    }

    public function shouldUse($name)
    {
    }

    public function fwd(): StatefulGuard|Guard
    {
        throw new \LogicException('not used');
    }

    public function getDefaultDriver(): string
    {
        return 'web';
    }

    public function user(): ?object
    {
        return $this->loggedOut ? null : new \stdClass();
    }

    public function logoutCurrentDevice(): void
    {
        $this->loggedOut = true;
    }
}
