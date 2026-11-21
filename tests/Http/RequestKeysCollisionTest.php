<?php

namespace MacropaySolutions\Kernel\Tests\Http;

use MacropaySolutions\Kernel\Http\Exceptions\HttpRequestException;
use MacropaySolutions\Kernel\Http\Request;
use MacropaySolutions\Kernel\Http\UploadedFile;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile as SymfonyUploadedFile;

/**
 * A field sent both in query and body, or both as a value and as a file, is found when the request is created and
 * kept in $keysCollisionException; it is thrown right away only with Request::REQUEST_THROWS_ON_KEYS_COLLISION.
 * Different keys, also inside the same nested array, are merged as before.
 */
class RequestKeysCollisionTest extends TestCase
{
    private const QUERY = 'A field was sent both in query and body.';
    private const FILE = 'A field was sent both as a value and as a file.';

    public function testACollisionIsKeptAndNotThrownByDefault(): void
    {
        foreach ($this->collisions() as $i => [$message, $uri, $input, $files]) {
            $request = Request::create($uri, 'POST', $input, [], $files);

            $this->assertSame($message, $request->keysCollisionException?->getMessage(), 'case ' . $i);
        }

        $request = Request::create('/', 'POST', ['avatar' => 'x'], [], ['avatar' => $this->file('a.jpg')]);
        $this->assertSame('x', $request->get('avatar'));
        $this->assertInstanceOf(UploadedFile::class, $request->all()['avatar']);
    }

    public function testACollisionIsThrownWhenTheRequestIsCreatedWithTheFlag(): void
    {
        foreach ($this->collisions() as $i => [$message, $uri, $input, $files]) {
            try {
                ThrowingOnKeysCollisionRequest::create($uri, 'POST', $input, [], $files);
                $this->fail('expected a HttpRequestException for case ' . $i);
            } catch (HttpRequestException $e) {
                $this->assertSame($message, $e->getMessage());
            }
        }

        $request = ThrowingOnKeysCollisionRequest::create('/?page=2', 'POST', ['name' => 'b']);
        $this->assertNull($request->keysCollisionException);

        try {
            $request->merge(['page' => '3']);
            $this->fail('expected a HttpRequestException after merge');
        } catch (HttpRequestException $e) {
            $this->assertSame(self::QUERY, $e->getMessage());
        }
    }

    public function testIntegerKeysOfQueryAndBodyArraysAreNotRenumbered(): void
    {
        $request = Request::create('/?docs[3]=x', 'POST', ['docs' => ['y']], [], ['docs' => [1 => $this->file('a')]]);

        $this->assertNull($request->keysCollisionException);
    }

    public function testDifferentKeysAreMerged(): void
    {
        $request = Request::create(
            '/?page=2',
            'POST',
            ['name' => 'b', 'profile' => ['bio' => 'text']],
            [],
            ['avatar' => $this->file('a.jpg'), 'profile' => ['photo' => $this->file('b.jpg')]]
        );
        $all = $request->all();

        $this->assertNull($request->keysCollisionException);
        $this->assertSame('2', $all['page']);
        $this->assertSame('text', $all['profile']['bio']);
        $this->assertInstanceOf(UploadedFile::class, $all['avatar']);
        $this->assertInstanceOf(UploadedFile::class, $all['profile']['photo']);
    }

    public function testTheSameValueSentTwiceIsNotACollision(): void
    {
        $request = Request::create('/?page=1&f[a]=x', 'POST', ['page' => '1', 'f' => ['a' => 'x', 'b' => 'y']]);

        $this->assertNull($request->keysCollisionException);
        $this->assertSame('1', $request->get('page'));

        $request = Request::create('/?page=1', 'POST', ['page' => ['1']]);
        $this->assertSame(self::QUERY, $request->keysCollisionException?->getMessage());
    }

    public function testAFilePartWithoutAnUploadIsNotACollision(): void
    {
        $noFile = ['name' => '', 'type' => '', 'tmp_name' => '', 'error' => \UPLOAD_ERR_NO_FILE, 'size' => 0];

        $this->assertNull(Request::create('/', 'POST', ['bio' => 'x'], [], ['bio' => $noFile])->keysCollisionException);
    }

    public function testInitializingAgainChecksTheNewInput(): void
    {
        $request = Request::create('/?name=a', 'POST', ['name' => 'b']);
        $request->initialize(['page' => '1'], ['name' => 'b']);

        $this->assertNull($request->keysCollisionException);
        $this->assertSame('b', Request::createFrom($request)->get('name'));

        // the fast path (no query, no files) resets it too
        $request = Request::create('/?name=a', 'POST', ['name' => 'b']);
        $request->initialize([], ['name' => 'b']);
        $this->assertNull($request->keysCollisionException);
    }

    public function testMergeReplaceAndOffsetSetCheckAgain(): void
    {
        foreach (
            [
                'merge' => static fn(Request $r) => $r->merge(['name' => 'b']),
                'replace' => static fn(Request $r) => $r->replace(['name' => 'b']),
                'offsetSet' => static function (Request $r): void {
                    $r['name'] = 'b';
                },
            ] as $method => $change
        ) {
            $request = Request::create('/?name=a', 'POST', ['page' => '1']);
            $this->assertNull($request->keysCollisionException);
            $change($request);

            $this->assertSame(self::QUERY, $request->keysCollisionException?->getMessage(), $method);
        }

        $request = Request::create('/', 'POST', [], [], ['avatar' => $this->file('a.jpg')]);
        $request->merge(['avatar' => 'x']);
        $this->assertSame(self::FILE, $request->keysCollisionException?->getMessage());
    }

    public function testChangesWithoutACollisionKeepNull(): void
    {
        $request = Request::create('/?name=a', 'POST', ['page' => '1']);
        $request->merge(['name' => 'a', 'limit' => '5']);
        $request->mergeIfMissing(['name' => 'b']);
        $request->forceReplace(['name' => 'b']);

        $this->assertNull($request->keysCollisionException);
        $this->assertSame('b', $request->get('name'));
    }

    public function testACollisionIsKeptAfterTheInputChanges(): void
    {
        $request = Request::create('/?name=a', 'POST', ['name' => 'b']);
        $request->replace(['page' => '1']);

        // the client did send the field twice, removing it later does not hide that
        $this->assertSame(self::QUERY, $request->keysCollisionException?->getMessage());
    }

    public function testForceReplaceClearsTheClientCollisionAndChecksTheNewDataAgainstTheFiles(): void
    {
        $request = Request::create('/?name=a', 'POST', ['name' => 'b']);
        $request->forceReplace(['name' => 'c']);
        $this->assertNull($request->keysCollisionException);

        $request = Request::create('/', 'POST', [], [], ['avatar' => $this->file('a.jpg')]);
        $request->forceReplace(['avatar' => 'x']);
        $this->assertSame(self::FILE, $request->keysCollisionException?->getMessage());
    }

    private function collisions(): array
    {
        return [
            [self::QUERY, '/?name=a', ['name' => 'b'], []],
            [self::QUERY, '/?profile[bio]=a', ['profile' => ['bio' => 'b']], []],
            [self::FILE, '/', ['avatar' => 'x'], ['avatar' => $this->file('a.jpg')]],
            [self::FILE, '/', ['avatar' => ''], ['avatar' => $this->file('a.jpg')]],
            [self::FILE, '/?avatar=x', [], ['avatar' => $this->file('a.jpg')]],
            [self::FILE, '/', ['docs' => ['/tmp/phpGGT8gz']], ['docs' => [$this->file('a.pdf')]]],
            [self::FILE, '/', ['profile' => ['bio' => 'x']], ['profile' => ['bio' => $this->file('a.txt')]]],
            // the file collides with the query part of an array the body also sends
            [self::FILE, '/?docs[3]=x', ['docs' => ['y']], ['docs' => [3 => $this->file('a.pdf')]]],
            [self::FILE, '/?p[lang]=en', ['p' => ['bio' => 'b']], ['p' => ['lang' => $this->file('b')]]],
        ];
    }

    private function file(string $name): SymfonyUploadedFile
    {
        return new SymfonyUploadedFile(__FILE__, $name, null, null, true);
    }
}

class ThrowingOnKeysCollisionRequest extends Request
{
    public const REQUEST_THROWS_ON_KEYS_COLLISION = true;
}
