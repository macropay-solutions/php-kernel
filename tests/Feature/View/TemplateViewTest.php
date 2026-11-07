<?php

namespace MacropaySolutions\Kernel\Tests\Feature\View;

use MacropaySolutions\Kernel\Contracts\View\Factory;
use MacropaySolutions\Kernel\Tests\ApplicationTestCase;

/**
 * Templates are compiled once into the compiled-views directory and rendered with escaping by default.
 */
class TemplateViewTest extends ApplicationTestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = \sys_get_temp_dir() . '/views-' . \bin2hex(\random_bytes(4));
        \mkdir($this->dir . '/views/partials', 0777, true);
        \mkdir($this->dir . '/compiled');

        $this->app->configure('view');
        $this->app->make('config')->set('view.paths', [$this->dir . '/views']);
        $this->app->make('config')->set('view.compiled', $this->dir . '/compiled');

        \file_put_contents($this->dir . '/views/partials/item.template.php', '<li>{{ $item }}</li>');
        \file_put_contents(
            $this->dir . '/views/list.template.php',
            '<h1>{{ $title }}</h1>{!! $raw !!}@if ($items)<ul>@foreach ($items as $item)'
                . "@include('partials.item', ['item' => \$item])@endforeach</ul>@else<p>empty</p>@endif"
        );
    }

    protected function tearDown(): void
    {
        \exec('rm -rf ' . \escapeshellarg($this->dir));

        parent::tearDown();
    }

    public function test_a_template_renders_escaped_raw_conditional_and_included_output(): void
    {
        $html = $this->views()->make('list', ['title' => '<b>Orders</b>', 'raw' => '<hr>', 'items' => ['a', 'b']])
            ->render();

        $this->assertSame('<h1>&lt;b&gt;Orders&lt;/b&gt;</h1><hr><ul><li>a</li><li>b</li></ul>', $html);
        $this->assertStringContainsString(
            '<p>empty</p>',
            $this->views()->make('list', ['title' => 'x', 'raw' => '', 'items' => []])->render()
        );
    }

    public function test_variables_are_added_with_with_not_with_magic_methods(): void
    {
        $view = $this->views()->make('list', ['raw' => '', 'items' => []])->with('title', 'Explicit');

        $this->assertStringContainsString('<h1>Explicit</h1>', $view->render());

        $this->expectException(\BadMethodCallException::class);

        $this->views()->make('list')->withTitle('Magic');
    }

    public function test_templates_are_compiled_once_and_recompiled_when_changed(): void
    {
        $this->views()->make('partials.item', ['item' => 'x'])->render();
        $compiled = \glob($this->dir . '/compiled/*.php');

        $this->assertCount(1, $compiled);

        $firstCompile = \filemtime($compiled[0]);
        \file_put_contents($this->dir . '/views/partials/item.template.php', '<li>[{{ $item }}]</li>');
        \touch($this->dir . '/views/partials/item.template.php', $firstCompile + 10);
        \clearstatcache();
        // A new request starts with an empty "already checked" list; within one request a view is checked once.
        $this->app->make('view.engine.resolver')->resolve('template')->forgetCompiledOrNotExpired();

        $this->assertSame('<li>[x]</li>', $this->views()->make('partials.item', ['item' => 'x'])->render());
        $this->assertCount(1, \glob($this->dir . '/compiled/*.php'));
    }

    public function test_view_exists_and_missing_views(): void
    {
        $this->assertTrue($this->views()->exists('partials.item'));
        $this->assertFalse($this->views()->exists('missing'));

        $this->expectException(\InvalidArgumentException::class);

        $this->views()->make('missing')->render();
    }

    private function views(): Factory
    {
        return $this->app->make(Factory::class);
    }
}
