<?php

namespace MacropaySolutions\Kernel\Tests\Feature\Filesystem;

use MacropaySolutions\Kernel\Contracts\Filesystem\Factory;
use MacropaySolutions\Kernel\Contracts\Filesystem\FileNotFoundException;
use MacropaySolutions\Kernel\Filesystem\Filesystem;
use MacropaySolutions\Kernel\Tests\ApplicationTestCase;

/**
 * Local disks resolved from the filesystem manager, and the Filesystem service used by the framework.
 */
class StorageTest extends ApplicationTestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = \sys_get_temp_dir() . '/kernel-storage-' . \bin2hex(\random_bytes(6));
        $this->app->configure('filesystems');
        $this->app->make('config')->set('filesystems.disks.local', [
            'driver' => 'local',
            'root' => $this->root . '/app',
        ]);
        $this->app->make('config')->set('filesystems.disks.public', [
            'driver' => 'local',
            'root' => $this->root . '/public',
            'url' => 'https://cdn.example.com/storage',
            'visibility' => 'public',
        ]);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->root);

        parent::tearDown();
    }

    public function test_put_get_exists_size_and_delete_on_the_default_disk(): void
    {
        $disk = $this->disks()->disk();

        $this->assertTrue($disk->put('reports/2026/nov.txt', 'hello'));
        $this->assertTrue($disk->exists('reports/2026/nov.txt'));
        $this->assertSame('hello', $disk->get('reports/2026/nov.txt'));
        $this->assertSame(5, $disk->size('reports/2026/nov.txt'));
        $this->assertFileExists($this->root . '/app/reports/2026/nov.txt');

        $this->assertTrue($disk->delete('reports/2026/nov.txt'));
        $this->assertFalse($disk->exists('reports/2026/nov.txt'));
    }

    public function test_append_prepend_copy_and_move(): void
    {
        $disk = $this->disks()->disk('local');
        $disk->put('log.txt', 'b');
        $disk->append('log.txt', 'c');
        $disk->prepend('log.txt', 'a');

        $this->assertSame("a\nb\nc", $disk->get('log.txt'));

        $disk->copy('log.txt', 'copy.txt');
        $disk->move('log.txt', 'archive/log.txt');

        $this->assertSame("a\nb\nc", $disk->get('copy.txt'));
        $this->assertFalse($disk->exists('log.txt'));
        $this->assertTrue($disk->exists('archive/log.txt'));
    }

    public function test_listing_files_and_directories(): void
    {
        $disk = $this->disks()->disk('local');
        $disk->put('a.txt', '1');
        $disk->put('dir/b.txt', '2');
        $disk->put('dir/sub/c.txt', '3');

        $this->assertSame(['a.txt'], $disk->files());
        $this->assertSame(['a.txt', 'dir/b.txt', 'dir/sub/c.txt'], $this->sorted($disk->allFiles()));
        $this->assertSame(['dir'], $disk->directories());
        $this->assertSame(['dir', 'dir/sub'], $this->sorted($disk->allDirectories()));

        $disk->deleteDirectory('dir');

        $this->assertSame(['a.txt'], $disk->allFiles());
    }

    public function test_reading_a_missing_file(): void
    {
        $disk = $this->disks()->disk('local');

        $this->assertNull($disk->get('missing.txt'));
        $this->assertFalse($disk->exists('missing.txt'));
    }

    public function test_public_disk_urls_and_visibility(): void
    {
        $disk = $this->disks()->disk('public');
        $disk->put('avatars/ana.png', 'png');

        $this->assertSame('https://cdn.example.com/storage/avatars/ana.png', $disk->url('avatars/ana.png'));
        $this->assertSame('public', $disk->getVisibility('avatars/ana.png'));
    }

    public function test_path_traversal_outside_the_disk_root_is_rejected(): void
    {
        $this->expectException(\Throwable::class);

        $this->disks()->disk('local')->put('../escaped.txt', 'x');
    }

    public function test_the_filesystem_service_reads_writes_and_requires_files(): void
    {
        $files = new Filesystem();
        $path = $this->root . '/raw/config.php';

        $files->ensureDirectoryExists(\dirname($path));
        $files->put($path, '<?php return ["debug" => true];');

        $this->assertTrue($files->exists($path));
        $this->assertSame(['debug' => true], $files->getRequire($path));
        $this->assertSame('php', $files->extension($path));
        $this->assertSame('config', $files->name($path));

        $files->replaceInFile('true', 'false', $path);
        $this->assertStringContainsString('false', $files->get($path));

        $this->expectException(FileNotFoundException::class);
        $files->get($this->root . '/raw/missing.php');
    }

    private function disks(): Factory
    {
        return $this->app->make(Factory::class);
    }

    private function sorted(array $values): array
    {
        \sort($values);

        return $values;
    }
}
