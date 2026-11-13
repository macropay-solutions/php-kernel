<?php

namespace MacropaySolutions\Kernel\Tests\Http;

use MacropaySolutions\Framework\Http\Request as FrameworkRequest;
use MacropaySolutions\Kernel\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The dispatcher stores the current route as an array [status, action, parameters]; the framework Request
 * (RequestTrait) reads that array. The kernel Request is only used without a route (ConsoleRequestMock):
 * its helpers must not fail there (they used to call methods of a route object that does not exist).
 */
class RequestArrayRouteTest extends TestCase
{
    public static function requestClassesProvider(): array
    {
        return [
            'framework request' => [FrameworkRequest::class],
        ];
    }

    public function testKernelRequestWithoutRouteDoesNotFail(): void
    {
        $request = $this->requestWithRoute(Request::class, null);

        $this->assertNull($request->route());
        $this->assertSame('default', $request->route('id', 'default'));
        $this->assertFalse($request->routeIs('*'));
        $this->assertTrue(isset($request['q']));
        $this->assertFalse(isset($request['id']));

        $this->expectException(RuntimeException::class);

        $request->fingerprint();
    }

    private function requestWithRoute(string $class, ?array $route, string $uri = '/users/5?q=1'): Request
    {
        /** @var Request $request */
        $request = $class::create($uri, 'GET', [], [], [], ['REMOTE_ADDR' => '10.0.0.1']);
        $request->setRouteResolver(static fn(): ?array => $route);

        return $request;
    }

    #[DataProvider('requestClassesProvider')]
    public function testRouteReturnsTheArrayAndItsParameters(string $class): void
    {
        $route = [1, ['as' => 'users.show', 'uses' => 'UserController@show'], ['id' => '5']];
        $request = $this->requestWithRoute($class, $route);

        $this->assertSame($route, $request->route());
        $this->assertSame('5', $request->route('id'));
        $this->assertSame('default', $request->route('missing', 'default'));
    }

    #[DataProvider('requestClassesProvider')]
    public function testRouteParameterWithPhpWrapperGivesDefault(string $class): void
    {
        $request = $this->requestWithRoute($class, [1, [], ['file' => 'php://filter/resource=x']]);

        $this->assertSame('default', $request->route('file', 'default'));
    }

    #[DataProvider('requestClassesProvider')]
    public function testRouteWithoutRouteGivesNullOrDefault(string $class): void
    {
        $request = $this->requestWithRoute($class, null);

        $this->assertNull($request->route());
        $this->assertSame('default', $request->route('id', 'default'));
    }

    #[DataProvider('requestClassesProvider')]
    public function testRouteIsMatchesTheRouteName(string $class): void
    {
        $request = $this->requestWithRoute($class, [1, ['as' => 'users.show'], []]);

        $this->assertTrue($request->routeIs('users.*'));
        $this->assertTrue($request->routeIs('posts.*', 'users.show'));
        $this->assertFalse($request->routeIs('posts.*'));
    }

    #[DataProvider('requestClassesProvider')]
    public function testRouteIsWithoutNameOrRouteIsFalse(string $class): void
    {
        $this->assertFalse($this->requestWithRoute($class, [1, [], []])->routeIs('*'));
        $this->assertFalse($this->requestWithRoute($class, null)->routeIs('*'));
    }

    #[DataProvider('requestClassesProvider')]
    public function testOffsetExistsSeesInputAndRouteParameters(string $class): void
    {
        $request = $this->requestWithRoute($class, [1, [], ['id' => '5']]);

        $this->assertTrue(isset($request['q']));
        $this->assertTrue(isset($request['id']));
        $this->assertFalse(isset($request['missing']));
        $this->assertFalse(isset($this->requestWithRoute($class, null)['id']));
    }

    #[DataProvider('requestClassesProvider')]
    public function testFingerprintUsesMethodRootPathAndIp(string $class): void
    {
        $request = $this->requestWithRoute($class, [1, [], ['id' => '5']]);

        $this->assertSame(
            \sha1(\implode('|', ['GET', 'http://localhost', 'users/5', '10.0.0.1'])),
            $request->fingerprint()
        );
    }

    #[DataProvider('requestClassesProvider')]
    public function testFingerprintWithoutRouteThrows(string $class): void
    {
        $this->expectException(RuntimeException::class);

        $this->requestWithRoute($class, null)->fingerprint();
    }
}
