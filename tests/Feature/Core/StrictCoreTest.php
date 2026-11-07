<?php

namespace MacropaySolutions\Kernel\Tests\Feature\Core;

use MacropaySolutions\Kernel\Contracts\Cache\Store;
use MacropaySolutions\Kernel\Contracts\Console\Kernel as ConsoleKernel;
use MacropaySolutions\Kernel\Container\EntryNotFoundException;
use MacropaySolutions\Kernel\Contracts\Container\BindingResolutionException;
use MacropaySolutions\Kernel\Events\Dispatcher;
use MacropaySolutions\Kernel\Support\Collection;
use MacropaySolutions\Kernel\Support\Optional;
use MacropaySolutions\Kernel\Tests\ApplicationTestCase;
use MacropaySolutions\Kernel\Tests\Fixtures\Container\Clock;
use MacropaySolutions\Kernel\Tests\Fixtures\Container\Factories;
use MacropaySolutions\Kernel\Tests\Fixtures\Container\FixedClock;
use MacropaySolutions\Kernel\Tests\Fixtures\Core\AbstractService;
use MacropaySolutions\Kernel\Tests\Fixtures\Core\BootCountingProvider;
use MacropaySolutions\Kernel\Tests\Fixtures\Core\BrokenConstructor;
use MacropaySolutions\Kernel\Tests\Fixtures\Core\NotInstantiable;
use MacropaySolutions\Kernel\Tests\Fixtures\Events\EventLog;
use MacropaySolutions\Kernel\Tests\Fixtures\Events\OrderShipped;
use Psr\Container\NotFoundExceptionInterface;

/**
 * V1 core breaking changes: closure-free container bindings, no closure event listeners or subscribers,
 * deferred/manual provider booting, no higher-order proxies and explicit ->fwd() instead of magic forwarding.
 */
class StrictCoreTest extends ApplicationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        EventLog::$entries = [];
        Factories::$made = 0;
        BootCountingProvider::$registered = 0;
        BootCountingProvider::$booted = 0;
    }

    public function test_resolving_an_interface_a_trait_or_a_missing_class_explains_why(): void
    {
        $messages = [];

        foreach ([Clock::class, NotInstantiable::class, 'App\\DoesNotExist', AbstractService::class] as $abstract) {
            try {
                $this->app->make($abstract);
            } catch (BindingResolutionException $e) {
                $messages[] = $e->getMessage();
            }
        }

        $this->assertSame([
            'Target interface [' . Clock::class . '] is not instantiable.',
            'Target trait [' . NotInstantiable::class . '] is not instantiable.',
            'Target class [App\\DoesNotExist] does not exist.',
            'Target class [' . AbstractService::class . '] is not instantiable.',
        ], $messages);
    }

    public function test_psr_get_reports_unresolvable_entries_as_not_found(): void
    {
        foreach (['App\\DoesNotExist', AbstractService::class, Clock::class] as $id) {
            try {
                $this->app->get($id);
                $this->fail($id . ' must not resolve.');
            } catch (EntryNotFoundException $e) {
                $this->assertInstanceOf(NotFoundExceptionInterface::class, $e);
                $this->assertInstanceOf(BindingResolutionException::class, $e->getPrevious());
            }
        }
    }

    public function test_psr_get_and_has_see_lazy_bindings(): void
    {
        $this->assertTrue($this->app->has('cache'));
        $this->assertFalse($this->app->has('App\\DoesNotExist'));
        $this->assertSame($this->app->make('cache'), $this->app->get('cache'));
    }

    public function test_psr_get_rethrows_failures_of_a_bound_entry(): void
    {
        $this->app->bind('broken', BrokenConstructor::class);

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Class "App\\MissingInsideConstructor" not found');

        $this->app->get('broken');
    }

    public function test_errors_from_inside_a_constructor_are_not_rewritten(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Class "App\\MissingInsideConstructor" not found');

        $this->app->make(BrokenConstructor::class);
    }

    public function test_bind_accepts_a_class_name_or_a_static_array_callable_but_not_a_closure(): void
    {
        $this->app->bind(Clock::class, [Factories::class, 'clock']);

        $this->assertSame('12:00', $this->app->make(Clock::class)->now());

        $this->expectException(\TypeError::class);

        $this->app->bind('clock.closure', static fn(): Clock => new FixedClock());
    }

    public function test_array_access_stores_objects_as_instances_and_strings_as_bindings(): void
    {
        $clock = new FixedClock('08:00');

        $this->app['clock.instance'] = $clock;
        $this->app['clock.class'] = FixedClock::class;

        $this->assertSame($clock, $this->app['clock.instance']);
        $this->assertNotSame($this->app['clock.class'], $this->app['clock.class']);
        $this->assertSame('10:00', $this->app['clock.class']->now());
    }

    public function test_a_singleton_made_with_parameters_is_neither_reused_nor_stored(): void
    {
        $this->app->singleton(FixedClock::class);

        $custom = $this->app->make(FixedClock::class, ['time' => '23:00']);
        $shared = $this->app->make(FixedClock::class);

        $this->assertSame('23:00', $custom->now());
        $this->assertSame('10:00', $shared->now());
        $this->assertSame($shared, $this->app->make(FixedClock::class));
        $this->assertNotSame($custom, $this->app->make(FixedClock::class, ['time' => '23:00']));
    }

    public function test_resolving_callbacks_run_once_for_a_singleton(): void
    {
        $this->app->singleton(FixedClock::class);
        $calls = 0;
        $this->app->afterResolving(FixedClock::class, static function () use (&$calls): void {
            $calls++;
        });

        $this->app->make(FixedClock::class);
        $this->app->make(FixedClock::class);

        $this->assertSame(1, $calls);
    }

    public function test_contextual_bindings_are_gone(): void
    {
        $this->assertFalse(\method_exists($this->app, 'when'));
        $this->assertFalse(\method_exists($this->app, 'addContextualBinding'));
    }

    public function test_closure_event_listeners_are_rejected(): void
    {
        $this->expectException(\TypeError::class);

        $this->app->make('events')->listen(OrderShipped::class, static function (): void {
        });
    }

    public function test_event_subscribers_are_gone(): void
    {
        $this->assertFalse(\method_exists(Dispatcher::class, 'subscribe'));
    }

    public function test_only_providers_passed_to_boot_are_booted(): void
    {
        $this->app->register(BootCountingProvider::class);

        $this->assertSame(1, BootCountingProvider::$registered);
        $this->assertSame(0, BootCountingProvider::$booted);

        $this->app->boot(BootCountingProvider::class);
        $this->app->boot(BootCountingProvider::class);

        $this->assertSame(1, BootCountingProvider::$registered);
        $this->assertSame(1, BootCountingProvider::$booted);
    }

    public function test_boot_can_boot_a_provider_that_was_never_registered(): void
    {
        $this->app->boot(BootCountingProvider::class);

        $this->assertSame(0, BootCountingProvider::$registered);
        $this->assertSame(1, BootCountingProvider::$booted);
        $this->assertFalse($this->app->providerIsLoaded(BootCountingProvider::class));
    }

    public function test_higher_order_collection_proxies_are_gone(): void
    {
        $this->expectException(\BadMethodCallException::class);

        (new Collection([1, 2]))->each;
    }

    public function test_tap_needs_a_callback(): void
    {
        $this->assertSame(5, \tap(5, static function (): void {
        }));

        $this->expectException(\ArgumentCountError::class);

        \tap(5);
    }

    public function test_the_str_helper_is_gone(): void
    {
        $this->assertFalse(\function_exists('str'));
    }

    public function test_the_cache_repository_no_longer_forwards_to_the_store(): void
    {
        $cache = $this->app->make('cache')->store();

        $this->assertInstanceOf(Store::class, $cache->fwd());

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Use ->fwd()->getPrefix() instead');

        $cache->getPrefix();
    }

    public function test_optional_forwards_only_through_fwd(): void
    {
        $clock = new FixedClock('09:00');

        $this->assertSame('09:00', (new Optional($clock))->fwd()->now());
        $this->assertNull((new Optional(null))->fwd());

        $this->expectException(\BadMethodCallException::class);

        (new Optional($clock))->now();
    }

    public function test_managers_still_forward_to_their_default_driver(): void
    {
        $this->app->make('cache')->put('greeting', 'hi', 60);

        $this->assertSame('hi', $this->app->make('cache')->get('greeting'));
        $this->assertSame('hi', $this->app->make('cache')->fwd()->get('greeting'));
    }

    public function test_queue_restart_is_gone(): void
    {
        $commands = \array_keys($this->app->make(ConsoleKernel::class)->all());

        $this->assertContains('queue:work', $commands);
        $this->assertNotContains('queue:restart', $commands);
    }
}
