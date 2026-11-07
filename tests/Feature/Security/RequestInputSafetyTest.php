<?php

namespace MacropaySolutions\Kernel\Tests\Feature\Security;

use MacropaySolutions\Kernel\Http\Request;
use PHPUnit\Framework\TestCase;

/**
 * Request input never hands out php:// or phar:// stream wrappers (file_get_contents($request->input('x'))
 * would otherwise read or deserialize arbitrary files), QUERY requests read their body like POST, and
 * forceReplace / forceOffsetUnset change both input bags at once.
 */
class RequestInputSafetyTest extends TestCase
{
    public function test_php_and_phar_wrappers_are_replaced_by_the_default(): void
    {
        $request = Request::create('/', 'POST', [
            'file' => 'php://filter/convert.base64-encode/resource=/etc/passwd',
            'upper' => '  PHP://input',
            'nested' => 'compress.zlib://phar:///tmp/evil.phar',
            'safe' => 'https://example.com/a.png',
        ]);

        $this->assertNull($request->input('file'));
        $this->assertSame('fallback', $request->input('file', 'fallback'));
        $this->assertNull($request->get('upper'));
        $this->assertNull($request->input('nested'));
        $this->assertSame('https://example.com/a.png', $request->input('safe'));
        $this->assertNull($request->file);
    }

    public function test_wrappers_inside_arrays_and_dangerous_keys_are_removed_from_all_input(): void
    {
        $request = Request::create('/', 'POST', [
            'docs' => ['ok.pdf', 'phar://evil.phar/x'],
            'phar://key' => 'value',
            'name' => 'Ana',
        ]);

        $all = $request->all();

        $this->assertSame(['ok.pdf', null], $all['docs']);
        $this->assertArrayNotHasKey('phar://key', $all);
        $this->assertSame('Ana', $all['name']);
        $this->assertSame(['name' => 'Ana'], $request->only('name'));
    }

    public function test_get_filtered_also_filters_wrappers(): void
    {
        $request = Request::create('/', 'GET', ['path' => 'php://memory', 'page' => '7']);

        $this->assertSame('', $request->getFiltered('path'));
        $this->assertSame(7, $request->getFiltered('page', null, \FILTER_VALIDATE_INT));
        $this->assertSame('7', $request->getFiltered('page', null, 'digits'));
    }

    public function test_a_query_request_reads_its_body_before_the_query_string(): void
    {
        $request = Request::create('/search?term=from-url', 'QUERY', ['term' => 'from-body', 'limit' => '5']);

        $this->assertSame('QUERY', $request->getRealMethod());
        $this->assertSame('from-body', $request->get('term'));
        $this->assertSame('from-body', $request->input('term'));
        $this->assertSame(5, $request->getFiltered('limit', null, \FILTER_VALIDATE_INT));
    }

    public function test_force_replace_and_force_offset_unset_change_both_bags(): void
    {
        $request = Request::create('/?a=1&b=2', 'POST', ['c' => '3']);

        $request->forceOffsetUnset('a');

        $this->assertFalse($request->query->has('a'));
        $this->assertNull($request->input('a'));

        $request->forceReplace(['z' => '9']);

        $this->assertSame([], $request->query->all());
        $this->assertSame(['z' => '9'], $request->all());
    }
}
