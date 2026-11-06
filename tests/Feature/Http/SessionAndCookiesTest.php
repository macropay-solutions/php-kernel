<?php

namespace MacropaySolutions\Kernel\Tests\Feature\Http;

use MacropaySolutions\Framework\Http\Request;
use MacropaySolutions\Kernel\Cookie\Middleware\EncryptCookies;
use MacropaySolutions\Kernel\Session\Middleware\StartSession;
use MacropaySolutions\Kernel\Session\Middleware\VerifyCsrfToken;
use MacropaySolutions\Kernel\Tests\ApplicationTestCase;
use MacropaySolutions\Kernel\Tests\Fixtures\Http\SessionTestController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sessions, flash data, CSRF protection and encrypted cookies across several requests.
 */
class SessionAndCookiesTest extends ApplicationTestCase
{
    /**
     * @var array<string, string>
     */
    private array $cookies = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->configure('session');
        $this->app->make('config')->set('session.driver', 'array');
        $this->app->middleware([EncryptCookies::class, StartSession::class]);
        $this->app->routeMiddleware(['csrf' => VerifyCsrfToken::class]);

        $router = $this->app->router;
        $router->post('/cart', SessionTestController::class . '@put');
        $router->get('/cart', SessionTestController::class . '@get');
        $router->get('/token', SessionTestController::class . '@token');
        $router->post('/submit', ['middleware' => 'csrf', 'uses' => SessionTestController::class . '@submit']);
        $router->get('/cookie', SessionTestController::class . '@setCookie');
        $router->get('/cookie/read', SessionTestController::class . '@readCookie');
    }

    public function test_session_data_survives_between_requests_and_flash_data_lives_for_one(): void
    {
        $this->send('POST', '/cart', ['item' => 'book']);

        $this->assertSame('{"cart":"book","status":"added"}', $this->send('GET', '/cart')->getContent());
        $this->assertSame('{"cart":"book","status":null}', $this->send('GET', '/cart')->getContent());
    }

    public function test_csrf_protection_rejects_a_missing_token_and_accepts_the_session_token(): void
    {
        $token = $this->send('GET', '/token')->getContent();

        $this->assertSame(419, $this->send('POST', '/submit')->getStatusCode());
        $this->assertSame(419, $this->send('POST', '/submit', ['_token' => 'wrong'])->getStatusCode());
        $this->assertSame('submitted', $this->send('POST', '/submit', ['_token' => $token])->getContent());
        $viaHeader = $this->send('POST', '/submit', [], ['HTTP_X_CSRF_TOKEN' => $token]);

        $this->assertSame('submitted', $viaHeader->getContent());
    }

    public function test_cookies_are_encrypted_in_the_response_and_decrypted_in_the_next_request(): void
    {
        $response = $this->send('GET', '/cookie');

        $this->assertNotSame('chocolate', $this->cookies['flavour'] ?? null);
        $this->assertStringNotContainsString('chocolate', (string)$response->headers->get('Set-Cookie'));
        $this->assertSame('chocolate', $this->send('GET', '/cookie/read')->getContent());
    }

    public function test_a_tampered_cookie_is_dropped(): void
    {
        $this->cookies['flavour'] = 'tampered';

        $this->assertSame('', $this->send('GET', '/cookie/read')->getContent());
    }

    private function send(string $method, string $uri, array $parameters = [], array $server = []): Response
    {
        $response = $this->app->handle(Request::create($uri, $method, $parameters, $this->cookies, [], $server));

        foreach ($response->headers->getCookies() as $cookie) {
            /** @var Cookie $cookie */
            $this->cookies[$cookie->getName()] = (string)$cookie->getValue();
        }

        return $response;
    }
}
