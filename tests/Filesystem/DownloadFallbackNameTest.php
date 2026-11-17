<?php

namespace MacropaySolutions\Kernel\Tests\Filesystem;

use MacropaySolutions\Framework\Http\ResponseFactory;
use MacropaySolutions\Kernel\Filesystem\FilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\HeaderUtils;

class DownloadFallbackNameTest extends TestCase
{
    public function testANameWithoutAsciiEquivalentGetsAFallback(): void
    {
        foreach ([FilesystemAdapter::class, ResponseFactory::class] as $class) {
            $instance = (new \ReflectionClass($class))->newInstanceWithoutConstructor();
            $fallbackName = new \ReflectionMethod($instance, 'fallbackName');

            $this->assertSame('__', $fallbackName->invoke($instance, '日本'));
            $this->assertSame(
                "attachment; filename=__; filename*=utf-8''%E6%97%A5%E6%9C%AC",
                HeaderUtils::makeDisposition('attachment', '日本', $fallbackName->invoke($instance, '日本'))
            );
            $this->assertSame('report.pdf', $fallbackName->invoke($instance, 'report.pdf'));
        }
    }
}
