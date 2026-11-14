<?php

use MacropaySolutions\Framework\Application;
use MacropaySolutions\Framework\Http\Request;
use PHPUnit\Framework\TestCase;

/**
 * url() with a full URL (e.g. a route on another domain) dropped the extra segments; they are now appended
 * before the query string and fragment, each one encoded.
 */
class UrlGeneratorFullUrlSegmentsTest extends TestCase
{
    protected function setUp(): void
    {
        $app = new Application();
        $app->instance(
            \MacropaySolutions\Kernel\Http\Request::class,
            Request::create('https://macropay-solutions.com', 'GET')
        );
    }

    protected function tearDown(): void
    {
        restore_error_handler();
        restore_exception_handler();

        parent::tearDown();
    }

    public function testSegmentsAreAppendedToAFullUrl(): void
    {
        $this->assertSame(
            'https://api.example.com/docs/guide/a%20b',
            url('https://api.example.com/docs', ['guide', 'a b'])
        );
        $this->assertSame(
            'https://api.example.com/docs/guide',
            url('https://api.example.com/docs/', ['guide'])
        );
    }

    public function testSegmentsGoBeforeTheQueryAndFragment(): void
    {
        $this->assertSame(
            'https://api.example.com/docs/guide?x=1#top',
            url('https://api.example.com/docs?x=1#top', ['guide'])
        );
    }

    public function testFullUrlWithoutSegmentsIsUnchanged(): void
    {
        $this->assertSame('https://api.example.com/docs?x=1', url('https://api.example.com/docs?x=1'));
    }

    public function testRelativePathIsUnchanged(): void
    {
        $this->assertSame('https://macropay-solutions.com/docs/guide/intro', url('docs', ['guide', 'intro']));
    }
}
