<?php

namespace MacropaySolutions\Kernel\Tests\Feature\Container;

use MacropaySolutions\Kernel\Container\Container;
use MacropaySolutions\Kernel\Contracts\Container\BindingResolutionException;
use MacropaySolutions\Kernel\Contracts\Container\CircularDependencyException;
use MacropaySolutions\Kernel\Tests\Fixtures\Container\CircularA;
use MacropaySolutions\Kernel\Tests\Fixtures\Container\Clock;
use MacropaySolutions\Kernel\Tests\Fixtures\Container\DetectingContainer;
use MacropaySolutions\Kernel\Tests\Fixtures\Container\Factories;
use MacropaySolutions\Kernel\Tests\Fixtures\Container\FixedClock;
use MacropaySolutions\Kernel\Tests\Fixtures\Container\LoggingMailer;
use MacropaySolutions\Kernel\Tests\Fixtures\Container\Mailer;
use MacropaySolutions\Kernel\Tests\Fixtures\Container\Newsletter;
use PHPUnit\Framework\TestCase;

class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = new Container();
        Factories::$made = 0;
    }

    public function test_an_interface_bound_to_a_class_is_built_with_its_dependencies(): void
    {
        $this->container->bind(Clock::class, FixedClock::class);

        $newsletter = $this->container->make(Newsletter::class);

        $this->assertInstanceOf(FixedClock::class, $newsletter->mailer->clock);
        $this->assertSame('noreply@example.com', $newsletter->mailer->from);
    }

    public function test_bind_builds_a_new_instance_and_singleton_reuses_one(): void
    {
        $this->container->bind(Clock::class, FixedClock::class);
        $this->assertNotSame($this->container->make(Clock::class), $this->container->make(Clock::class));

        $this->container->singleton(Clock::class, FixedClock::class);
        $this->assertSame($this->container->make(Clock::class), $this->container->make(Clock::class));
    }

    public function test_a_static_factory_callable_builds_the_service(): void
    {
        $this->container->singleton(Clock::class, [Factories::class, 'clock']);

        $this->assertSame('12:00', $this->container->make(Clock::class)->now());
        $this->container->make(Clock::class);
        $this->assertSame(1, Factories::$made);
    }

    public function test_make_with_parameters_overrides_constructor_arguments(): void
    {
        $clock = $this->container->make(FixedClock::class, ['time' => '23:59']);

        $this->assertSame('23:59', $clock->now());
    }

    public function test_instances_aliases_and_bound(): void
    {
        $clock = new FixedClock('08:00');
        $this->container->instance(Clock::class, $clock);
        $this->container->alias(Clock::class, 'clock');

        $this->assertTrue($this->container->bound('clock'));
        $this->assertSame($clock, $this->container->make('clock'));
        $this->assertSame($clock, $this->container['clock']);
    }

    public function test_bind_if_and_singleton_if_do_not_override(): void
    {
        $this->container->bind(Clock::class, FixedClock::class);
        $this->container->singletonIf(Clock::class, [Factories::class, 'clock']);

        $this->assertSame('10:00', $this->container->make(Clock::class)->now());
        $this->assertSame(0, Factories::$made);
    }

    public function test_scoped_instances_are_forgotten_between_scopes(): void
    {
        $this->container->scoped(Clock::class, FixedClock::class);
        $first = $this->container->make(Clock::class);

        $this->assertSame($first, $this->container->make(Clock::class));

        $this->container->forgetScopedInstances();

        $this->assertNotSame($first, $this->container->make(Clock::class));
    }

    public function test_extend_decorates_the_resolved_service(): void
    {
        $this->container->bind(Clock::class, FixedClock::class);
        $this->container->singleton(Mailer::class);
        $this->container->extend(Mailer::class, [Factories::class, 'logging']);

        $mailer = $this->container->make(Mailer::class);

        $this->assertInstanceOf(LoggingMailer::class, $mailer);
        $this->assertSame('logging@example.com', $mailer->from);
        $this->assertSame($mailer, $this->container->make(Mailer::class));
    }

    public function test_tagged_services_are_resolved_together(): void
    {
        $this->container->bind(Clock::class, FixedClock::class);
        $this->container->tag([FixedClock::class, Mailer::class], 'reports');

        $tagged = \iterator_to_array($this->container->tagged('reports'));

        $this->assertCount(2, $tagged);
        $this->assertInstanceOf(FixedClock::class, $tagged[0]);
        $this->assertInstanceOf(Mailer::class, $tagged[1]);
    }

    public function test_call_injects_method_dependencies_and_named_parameters(): void
    {
        $this->container->bind(Clock::class, FixedClock::class);
        $newsletter = $this->container->make(Newsletter::class);

        $this->assertSame('News at 10:00', $this->container->call([$newsletter, 'send']));
        $this->assertSame('Sale at 10:00', $this->container->call([$newsletter, 'send'], ['subject' => 'Sale']));
    }

    public function test_an_unbound_interface_cannot_be_resolved(): void
    {
        $this->expectException(BindingResolutionException::class);
        $this->expectExceptionMessage('Target interface [' . Clock::class . '] is not instantiable.');

        $this->container->make(Mailer::class);
    }

    public function test_circular_dependencies_are_detected_when_the_memory_limit_is_set(): void
    {
        $this->expectException(CircularDependencyException::class);
        $this->expectExceptionMessage('Circular dependency detected while resolving');

        (new DetectingContainer())->make(CircularA::class);
    }

    public function test_forget_instance_and_flush(): void
    {
        $this->container->singleton(Clock::class, FixedClock::class);
        $first = $this->container->make(Clock::class);

        $this->container->forgetInstance(Clock::class);
        $this->assertNotSame($first, $this->container->make(Clock::class));

        $this->container->flush();
        $this->assertFalse($this->container->bound(Clock::class));
    }
}
