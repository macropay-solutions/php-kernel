<?php

namespace MacropaySolutions\Kernel\Tests\Translation;

use MacropaySolutions\Kernel\Filesystem\Filesystem;
use MacropaySolutions\Kernel\Translation\FileLoader;
use MacropaySolutions\Kernel\Translation\Translator;
use PHPUnit\Framework\TestCase;

class FileLoaderUnsafePathsAndOverridesTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = \sys_get_temp_dir() . '/kernel-lang-' . \uniqid();
        $this->write('app/lang/en/messages.php', ['hello' => 'Hello']);
        $this->write('app/lang/en/admin/users.php', ['title' => 'Users']);
        $this->write('uploads/messages.php', ['hello' => 'from an uploaded file']);
        $this->write('pkg/en/m.php', ['a' => 'package A', 'b' => 'package B']);
        $this->write('first/vendor/pkg/en/m.php', ['a' => 'overridden A']);
        \mkdir($this->root . '/second');
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->root);
    }

    public function testALocaleCannotLeaveTheLangPath(): void
    {
        $translator = new Translator(new FileLoader(new Filesystem(), $this->root . '/app/lang'), 'en');

        $this->assertSame('messages.hello', $translator->get('messages.hello', [], '../../uploads'));
        $this->assertSame('Hello', $translator->get('messages.hello'));
        $this->assertSame('Users', $translator->get('admin/users.title'));
    }

    public function testAnOverrideInAnEarlierPathIsKept(): void
    {
        $loader = new FileLoader(new Filesystem(), [$this->root . '/first', $this->root . '/second']);
        $loader->addNamespace('pkg', $this->root . '/pkg');

        $this->assertSame(['a' => 'overridden A', 'b' => 'package B'], $loader->load('en', 'm', 'pkg'));
    }

    private function write(string $file, array $lines): void
    {
        $path = $this->root . '/' . $file;
        @\mkdir(\dirname($path), 0777, true);
        \file_put_contents($path, '<?php return ' . \var_export($lines, true) . ';');
    }
}
