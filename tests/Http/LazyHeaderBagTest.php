<?php

namespace MacropaySolutions\Kernel\Tests\Http;

use MacropaySolutions\Kernel\Http\LazyHeaderBag;
use MacropaySolutions\Kernel\Http\Request;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\ServerBag;

/**
 * The lazy bag must answer every HeaderBag call exactly like the bag SymfonyRequest builds from ServerBag.
 */
class LazyHeaderBagTest extends TestCase
{
    private array $globalServer;

    protected function setUp(): void
    {
        $this->globalServer = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->globalServer;
    }

    public function testACapturedRequestGetsTheLazyBagAndABuiltOneDoesNot(): void
    {
        $_SERVER = ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/', 'HTTP_HOST' => 'example.com'];

        $this->assertInstanceOf(LazyHeaderBag::class, (new Request([], [], [], [], [], $_SERVER))->headers);
        $this->assertNotInstanceOf(LazyHeaderBag::class, Request::create('/')->headers);
        $this->assertSame('example.com', Request::capture()->headers->get('Host'));
    }

    public function testADigestAuthorizationGetsTheEagerBagBecauseGetHeadersWritesTheServerBag(): void
    {
        $_SERVER = ['HTTP_AUTHORIZATION' => 'Digest username="a"'];
        $request = new Request([], [], [], [], [], $_SERVER);

        $this->assertNotInstanceOf(LazyHeaderBag::class, $request->headers);
        $this->assertSame('Digest username="a"', $request->server->get('PHP_AUTH_DIGEST'));
    }

    public function testEveryReadMatchesTheEagerBag(): void
    {
        \mt_srand(20261124);

        for ($case = 0; $case < 300; $case++) {
            $server = $this->randomServer();

            foreach ($this->keysToRead($server) as $key) {
                [$lazy, $eager] = $this->bags($server);
                $this->assertSame($eager->get($key), $lazy->get($key), "get($key) case $case");
                $this->assertSame($eager->get($key, 'dflt'), $lazy->get($key, 'dflt'), "get($key, dflt) case $case");
                $this->assertSame($eager->has($key), $lazy->has($key), "has($key) case $case");
                $this->assertSame($eager->all($key), $lazy->all($key), "all($key) case $case");
                $this->assertSame(
                    $eager->contains($key, 'v1'),
                    $lazy->contains($key, 'v1'),
                    "contains($key) case $case"
                );
            }

            foreach (['all', 'keys', 'count', 'iterator', 'string', 'cache'] as $read) {
                [$lazy, $eager] = $this->bags($server);
                $this->assertSame($this->read($eager, $read), $this->read($lazy, $read), "$read() case $case");
            }
        }
    }

    public function testChangesMatchTheEagerBag(): void
    {
        \mt_srand(424242);

        for ($case = 0; $case < 200; $case++) {
            $server = $this->randomServer();
            [$lazy, $eager] = $this->bags($server);

            foreach ($this->randomChanges() as [$method, $arguments]) {
                $eager->$method(...$arguments);
                $lazy->$method(...$arguments);
            }

            $this->assertSame($eager->all(), $lazy->all(), "all() after changes, case $case");

            foreach ($this->keysToRead($server) as $key) {
                $this->assertSame($eager->get($key), $lazy->get($key), "get($key) after changes, case $case");
                $this->assertSame($eager->has($key), $lazy->has($key), "has($key) after changes, case $case");
            }

            $this->assertSame($this->read($eager, 'cache'), $this->read($lazy, 'cache'), "cache, case $case");
        }
    }

    public function testTheHeadersAreASnapshotOfTheServerVariablesLikeTheEagerBag(): void
    {
        $_SERVER = ['HTTP_X_TOKEN' => 'first'];
        $request = new Request([], [], [], [], [], $_SERVER);
        $request->server->set('HTTP_X_TOKEN', 'second');
        $_SERVER['HTTP_X_TOKEN'] = 'third';

        $this->assertSame('first', $request->headers->get('X-Token'));
    }

    public function testCloningKeepsTheBagsApart(): void
    {
        $_SERVER = ['HTTP_X_TOKEN' => 'a'];
        $request = new Request([], [], [], [], [], $_SERVER);
        $copy = clone $request;
        $copy->headers->set('X-Token', 'b');

        $this->assertSame('a', $request->headers->get('X-Token'));
        $this->assertSame('b', $copy->headers->get('X-Token'));
    }

    private function bags(array $server): array
    {
        $_SERVER = $server;

        return [
            (new Request([], [], [], [], [], $_SERVER))->headers,
            new HeaderBag((new ServerBag($server))->getHeaders()),
        ];
    }

    private function read(HeaderBag $bag, string $read): mixed
    {
        return match ($read) {
            'all' => $bag->all(),
            'keys' => $bag->keys(),
            'count' => $bag->count(),
            'iterator' => \iterator_to_array($bag),
            'string' => (string)$bag,
            'cache' => [
                $bag->hasCacheControlDirective('no-cache'),
                $bag->getCacheControlDirective('max-age'),
                $bag->getCacheControlDirective('private'),
            ],
        };
    }

    private function randomServer(): array
    {
        $server = ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/x', 'SCRIPT_NAME' => '/index.php'];
        $pool = [
            'HTTP_HOST' => ['example.com', ''],
            'HTTP_ACCEPT' => ['application/json', '*/*'],
            'HTTP_X_FORWARDED_FOR' => ['1.2.3.4', '1.2.3.4, 5.6.7.8'],
            'HTTP_X_CUSTOM_HEADER' => ['v1', '', 'v2'],
            'HTTP_CACHE_CONTROL' => ['no-cache', 'max-age=10, private', ''],
            'HTTP_IF_MODIFIED_SINCE' => ['Tue, 24 Nov 2026 10:00:00 GMT'],
            'HTTP_CONTENT_TYPE' => ['text/plain'],
            'HTTP_CONTENT_LENGTH' => ['5'],
            'CONTENT_TYPE' => ['application/json', ''],
            'CONTENT_LENGTH' => ['10', ''],
            'CONTENT_MD5' => ['abc', ''],
            'HTTP_AUTHORIZATION' => ['Bearer token', 'Basic ' . \base64_encode('user:pass'), 'Basic bad', 'Other x'],
            'REDIRECT_HTTP_AUTHORIZATION' => ['Bearer redirected'],
            'PHP_AUTH_USER' => ['u'],
            'PHP_AUTH_PW' => ['p'],
            'HTTP_PROXY' => ['http://proxy'],
            'PATH' => ['/usr/bin'],
            'APP_KEY' => ['secret'],
            'HTTP_NUMERIC' => [12],
            'HTTP_PHP_AUTH_USER' => ['header-user'],
            'HTTP_PHP_AUTH_PW' => ['header-pw'],
            'HTTP_PHP_AUTH_DIGEST' => ['header-digest'],
            'PHP_AUTH_DIGEST' => ['server-digest'],
            'HTTP_CONTENT_MD5' => ['md5-header'],
            'HTTP_NULL_VALUE' => [null],
        ];
        $poolKeys = \array_keys($pool);
        // the order of the server variables decides when two of them give the same header
        \shuffle($poolKeys);
        $pool = \array_replace(\array_flip($poolKeys), $pool);

        foreach ($pool as $key => $values) {
            if (\mt_rand(0, 2) > 0) {
                $server[$key] = $values[\mt_rand(0, \count($values) - 1)];
            }
        }

        return $server;
    }

    private function keysToRead(array $server): array
    {
        $keys = [
            'Host', 'host', 'HOST', 'Accept', 'X-Forwarded-For', 'x_forwarded_for', 'X-Custom-Header', 'Cache-Control',
            'If-Modified-Since', 'Content-Type', 'content_type', 'Content-Length', 'Content-MD5', 'Authorization',
            'PHP_AUTH_USER', 'php-auth-pw', 'PHP_AUTH_DIGEST', 'Proxy', 'Path', 'App-Key', 'Numeric', 'Missing', '',
            'Null-Value', 'Content_Md5',
        ];

        foreach (\array_keys($server) as $serverKey) {
            if (\str_starts_with($serverKey, 'HTTP_')) {
                $keys[] = \substr($serverKey, 5);
            }
        }

        return $keys;
    }

    private function randomChanges(): array
    {
        $changes = [
            ['set', ['X-Custom-Header', 'changed']],
            ['set', ['Accept', ['a', 'b']]],
            ['set', ['X-New', 'n', false]],
            ['set', ['Host', null]],
            ['remove', ['Host']],
            ['remove', ['Authorization']],
            ['add', [['X-Added' => 'x', 'Accept' => 'y']]],
            ['replace', [['Only' => 'one']]],
            ['addCacheControlDirective', ['max-age', '60']],
            ['removeCacheControlDirective', ['no-cache']],
        ];
        $picked = [];

        for ($i = \mt_rand(1, 4); $i > 0; $i--) {
            $picked[] = $changes[\mt_rand(0, \count($changes) - 1)];
        }

        return $picked;
    }
}
