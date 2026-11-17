<?php

namespace MacropaySolutions\Kernel\Tests\Http;

use MacropaySolutions\Kernel\Http\Middleware\SetCacheHeaders;
use MacropaySolutions\Kernel\Http\Request;
use MacropaySolutions\Kernel\Http\Response;
use MacropaySolutions\Kernel\Http\UploadedFile;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile as SymfonyUploadedFile;

class RequestAcceptUploadPathAndHeadCacheTest extends TestCase
{
    public function testAChangedAcceptHeaderIsSeen(): void
    {
        $request = Request::create('/', 'GET', server: ['HTTP_ACCEPT' => 'text/html']);
        $this->assertFalse($request->wantsJson());

        $request->headers->set('Accept', 'application/json');

        $this->assertTrue($request->wantsJson());
    }

    public function testADirectoryUploadKeepsItsPath(): void
    {
        $path = \tempnam(\sys_get_temp_dir(), 'upload');
        $file = UploadedFile::createFromBase(
            new SymfonyUploadedFile($path, 'docs/2024/report.txt', 'text/plain', null, true)
        );
        \unlink($path);

        $this->assertSame('docs/2024/report.txt', $file->getClientOriginalPath());
        $this->assertSame('report.txt', $file->getClientOriginalName());
    }

    public function testHeadResponsesGetCacheHeaders(): void
    {
        $response = (new SetCacheHeaders())->handle(
            Request::create('/', 'HEAD'),
            static fn() => new Response(),
            'max_age=120'
        );

        $this->assertSame(120, $response->getMaxAge());
    }
}
