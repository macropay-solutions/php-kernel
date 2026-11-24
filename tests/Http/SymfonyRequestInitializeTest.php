<?php

namespace MacropaySolutions\Kernel\Tests\Http;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

/**
 * The kernel Request::initialize() replaces SymfonyRequest::initialize() with a copy that builds a lazy header
 * bag. When this fails, Symfony changed its initialize(): bring the change into the kernel copy, then update
 * the expected body below.
 */
class SymfonyRequestInitializeTest extends TestCase
{
    private const EXPECTED_BODY = <<<'PHP'
        $this->request = new InputBag($request);
        $this->query = new InputBag($query);
        $this->attributes = new ParameterBag($attributes);
        $this->cookies = new InputBag($cookies);
        $this->files = new FileBag($files);
        $this->server = new ServerBag($server);
        $this->headers = new HeaderBag($this->server->getHeaders());

        $this->content = $content;
        $this->languages = null;
        $this->charsets = null;
        $this->encodings = null;
        $this->acceptableContentTypes = null;
        $this->pathInfo = null;
        $this->requestUri = null;
        $this->baseUrl = null;
        $this->basePath = null;
        $this->method = null;
        $this->format = null;
        PHP;

    public function testTheKernelCopyOfSymfonyInitializeIsUpToDate(): void
    {
        $method = new \ReflectionMethod(SymfonyRequest::class, 'initialize');
        $lines = \array_slice(
            \file((string)$method->getFileName()),
            $method->getStartLine() - 1,
            $method->getEndLine() - $method->getStartLine() + 1
        );
        $source = \implode('', $lines);
        $body = \substr($source, \strpos($source, '{') + 1, \strrpos($source, '}') - \strpos($source, '{') - 1);

        $this->assertSame(self::normalize(self::EXPECTED_BODY), self::normalize($body));
        $this->assertSame(
            'public function initialize(array $query = [], array $request = [], array $attributes = [], '
            . 'array $cookies = [], array $files = [], array $server = [], $content = null): void',
            \preg_replace('/\s+/', ' ', \trim(\substr($source, 0, \strpos($source, '{'))))
        );
    }

    /**
     * Blank lines and indentation do not matter; every statement does.
     */
    private static function normalize(string $code): string
    {
        return \implode("\n", \array_values(\array_filter(\array_map('trim', \explode("\n", $code)), 'strlen')));
    }
}
