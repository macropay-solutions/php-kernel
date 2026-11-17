<?php

namespace MacropaySolutions\Kernel\Tests\Container;

use MacropaySolutions\Kernel\Container\Container;
use MacropaySolutions\Kernel\Queue\CallQueuedCallable;
use PHPUnit\Framework\TestCase;

class DefaultParameterPrecedenceForClosuresTest extends TestCase
{
    public function testAClosureGetsTheDefaultForAnUnboundClassWhenTheFlagIsOn(): void
    {
        $container = new DefaultParameterPrecedenceForClosuresTestContainer();

        $this->assertNull(
            $container->call(static fn(?DefaultParameterPrecedenceForClosuresTestService $s = null) => $s)
        );
    }

    public function testABoundClassIsStillInjectedWhenTheFlagIsOn(): void
    {
        $container = new DefaultParameterPrecedenceForClosuresTestContainer();
        $container->instance(
            DefaultParameterPrecedenceForClosuresTestService::class,
            $service = new DefaultParameterPrecedenceForClosuresTestService()
        );

        $this->assertSame(
            $service,
            $container->call(static fn(?DefaultParameterPrecedenceForClosuresTestService $s = null) => $s)
        );
    }

    public function testADeferredBindingIsInjectedWhenTheFlagIsOn(): void
    {
        $container = new DefaultParameterPrecedenceForClosuresTestContainer();
        $container->deferred = [DefaultParameterPrecedenceForClosuresTestService::class];
        $target = [DefaultParameterPrecedenceForClosuresTestHandler::class, 'handle'];

        $this->assertTrue(
            $container->call(static fn(?DefaultParameterPrecedenceForClosuresTestService $s = null) => $s)
            instanceof DefaultParameterPrecedenceForClosuresTestService
        );
        $this->assertTrue(
            $container->call($target) instanceof DefaultParameterPrecedenceForClosuresTestService
        );
        $this->assertTrue(
            CallQueuedCallable::invoke($target, ['x'], [], $container)
            instanceof DefaultParameterPrecedenceForClosuresTestService
        );
    }

    public function testTheClassIsBuiltWhenTheFlagIsOff(): void
    {
        $this->assertTrue(
            (new Container())->call(static fn(?DefaultParameterPrecedenceForClosuresTestService $s = null) => $s)
            instanceof DefaultParameterPrecedenceForClosuresTestService
        );
    }
}

class DefaultParameterPrecedenceForClosuresTestContainer extends Container
{
    public const DEFAULT_PARAMETER_TAKES_PRECEDENCE_WHEN_AUTOWIRING = true;

    /**
     * Bindings registered on first use, like the application's deferred providers.
     */
    public array $deferred = [];

    public function has(string $id): bool
    {
        return \in_array($id, $this->deferred, true) || parent::has($id);
    }

    public function make($abstract, array $parameters = [])
    {
        if (\in_array($abstract, $this->deferred, true) && !$this->bound($abstract)) {
            $this->singleton($abstract);
        }

        return parent::make($abstract, $parameters);
    }
}

class DefaultParameterPrecedenceForClosuresTestService
{
}

class DefaultParameterPrecedenceForClosuresTestHandler
{
    public function handle(string $value = '', ?DefaultParameterPrecedenceForClosuresTestService $s = null): mixed
    {
        return $s;
    }
}
