<?php

namespace MacropaySolutions\Kernel\Queue;

use MacropaySolutions\Framework\Bus\PendingCallableDispatch;
use MacropaySolutions\Kernel\Bus\Batchable;
use MacropaySolutions\Kernel\Bus\InstanceDispatchable;
use MacropaySolutions\Kernel\Bus\Queueable;
use MacropaySolutions\Kernel\Container\BoundMethod;
use MacropaySolutions\Kernel\Contracts\Container\BindingResolutionException;
use MacropaySolutions\Kernel\Contracts\Container\Container;
use MacropaySolutions\Kernel\Contracts\Queue\ShouldQueue;

/**
 * DO NOT ADD PROPERTY HOOKS IN THIS CLASS OR IN ITS TRAITS TO ALLOW OBJECT RECONSTRUCTION AFTER DESERIALIZATION!
 */
class CallQueuedCallable implements ShouldQueue
{
    use InstanceDispatchable;
    use Batchable;
    use InteractsWithQueue;
    use Queueable;

    /**
     * [Class, Method, MethodNamedParamsMap]
     */
    public array $storableCallable;

    /**
     * The callables array that should be executed on failure.
     */
    public array $failureCallbacks = [];

    /**
     * Create a new job instance.
     * @throws \InvalidArgumentException
     */
    public function __construct(array $callable)
    {
        $this->storableCallable = Queue::storableCallable($callable);
    }

    /**
     * Create a new job instance.
     * @throws \InvalidArgumentException
     */
    public static function create(array $job): static
    {
        return new static($job);
    }

    public function dispatch(): PendingCallableDispatch
    {
        return new PendingCallableDispatch($this);
    }

    /**
     * Execute the job.
     */
    public function handle(Container $container): void
    {
        $systemArgs = [
            self::class => $this,
        ];

        if (($this->job ?? null) instanceof \MacropaySolutions\Kernel\Contracts\Queue\Job) {
            $systemArgs['job'] = $systemArgs[\MacropaySolutions\Kernel\Contracts\Queue\Job::class] = $systemArgs[$this->job::class] =
                $this->job;
        }

        static::invoke(
            [$this->storableCallable[0], $this->storableCallable[1]],
            $systemArgs,
            $this->storableCallable[2],
            $container
        );
    }

    /**
     * Add a callback to be executed if the job fails.
     */
    public function onFailure(array $callback): static
    {
        $this->failureCallbacks[] = Queue::storableCallable($callback);

        return $this;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $e): void
    {
        foreach ($this->failureCallbacks as $callback) {
            CallQueuedCallable::invokeFailure($callback, $e, []);
        }
    }

    /**
     * Get the display name for the queued job.
     */
    public function displayName(): string
    {
        return $this->storableCallable[0] . '@' . $this->storableCallable[1];
    }

    /**
     * When $systemArgs is not empty list, contextual bindings will be ignored
     * @throws BindingResolutionException
     */
    public static function invoke(array $target, array $systemArgs, array $userArgs, Container $container = null): mixed
    {
        $container ??= \MacropaySolutions\Kernel\Container\Container::getInstance();

        if ([] !== $systemArgs && \array_is_list($systemArgs)) {
            return self::manualInvoke($target, $userArgs, $systemArgs, $container, 0);
        }

        try {
            return $container->call($target, \array_merge($systemArgs, $userArgs));
        } catch (BindingResolutionException | \ReflectionException $exception) {
            return self::manualInvoke($target, $userArgs, $systemArgs, $container, null, $exception);
        }
    }

    /**
     * When $systemArgs is not empty list, contextual bindings will be ignored
     * @throws BindingResolutionException
     */
    public static function invokeFailure(array $callback, \Throwable $e, array $systemArgs): void
    {
        $container = \MacropaySolutions\Kernel\Container\Container::getInstance();
        $target = [$callback[0], $callback[1]];

        if ([] !== $systemArgs && \array_is_list($systemArgs)) {
            static::manualInvokeFailure($callback, $e, $systemArgs, $container, $target, 0);

            return;
        }

        try {
            $container->call($target, \array_merge($systemArgs, [
                'e' => $e,
                'ex' => $e,
                'exception' => $e,
                'error' => $e,
                \Throwable::class => $e,
                \Exception::class => $e,
            ], $callback[2] ?? []));
        } catch (BindingResolutionException | \ReflectionException $exception) {
            static::manualInvokeFailure($callback, $e, $systemArgs, $container, $target, null, $exception);
        }
    }

    /**
     * @throws BindingResolutionException
     */
    protected static function manualInvokeFailure(
        array $callback,
        \Throwable $e,
        mixed $systemArgs,
        \MacropaySolutions\Kernel\Container\Container $container,
        array $target,
        ?int $systemArgIndex = null,
        \ReflectionException | BindingResolutionException | \Exception | null $exception = null
    ): void {
        $args = [];

        foreach (
            BoundMethod::getAndCachePrecompiledAutoWiringClassMethodParametersMapForClassAndMethod(
                $callback[0],
                $callback[1]
            ) as $paramName => $details
        ) {
            if (\array_key_exists($paramName, $callback[2])) {
                $args[] = $callback[2][$paramName];

                continue;
            }

            if (
                \in_array($details['c'] ?? '', [\Throwable::class, \Exception::class], true)
                && !($details['v'] ?? false)
            ) {
                $args[] = $e;

                continue;
            }

            if (\array_key_exists($paramName, $systemArgs)) {
                $args[] = $systemArgs[$paramName];

                continue;
            }

            if (isset($details['c']) && \array_key_exists($details['c'], $systemArgs)) {
                $args[] = $systemArgs[$details['c']];

                continue;
            }

            if (isset($systemArgIndex) && \array_key_exists($systemArgIndex, $systemArgs)) {
                $currentSysArg = $systemArgs[$systemArgIndex];

                // If the parameter needs a specific class, ensure the system arg matches
                if (!isset($details['c'])) {
                    // It's a primitive parameter, safely consume the primitive system arg
                    $args[] = $currentSysArg;
                    $systemArgIndex++;

                    continue;
                }

                if (\is_object($currentSysArg) && $currentSysArg instanceof $details['c']) {
                    $args[] = $currentSysArg;
                    $systemArgIndex++;

                    continue;
                }
            }

            if (isset($details['c'])) {
                if (
                    $container::DEFAULT_PARAMETER_TAKES_PRECEDENCE_WHEN_AUTOWIRING
                    && \array_key_exists('d', $details)
                ) {
                    $args[] = $details['d'];

                    continue;
                }

                try {
                    $args[] = $container->make($details['c']);
                } catch (BindingResolutionException $ex) {
                    if (\array_key_exists('d', $details)) {
                        $args[] = $details['d'];

                        continue;
                    }

                    throw $ex;
                }

                continue;
            }

            if (
                !$container::DEFAULT_PARAMETER_TAKES_PRECEDENCE_WHEN_AUTOWIRING
                && \array_key_exists('d', $details)
            ) {
                $args[] = $details['d'];

                continue;
            }

            throw new BindingResolutionException(
                'Unresolvable dependency resolving [' . $paramName .
                '] in callable [' . $target[0] . '::' . $target[1] . ']. ' . $exception?->getMessage()
            );
        }

        if (!\is_callable($target)) {
            $target[0] = $container->make($target[0]);
        }

        \call_user_func_array($target, $args);
    }

    /**
     * @throws BindingResolutionException
     */
    protected static function manualInvoke(
        array $target,
        array $userArgs,
        array $systemArgs,
        \MacropaySolutions\Kernel\Container\Container $container,
        ?int $systemArgIndex = null,
        \ReflectionException | BindingResolutionException | \Exception | null $exception = null
    ): mixed {
        $args = [];

        foreach (
            BoundMethod::getAndCachePrecompiledAutoWiringClassMethodParametersMapForClassAndMethod(
                $target[0],
                $target[1]
            ) as $paramName => $details
        ) {
            if (\array_key_exists($paramName, $userArgs)) {
                $args[] = $userArgs[$paramName];

                continue;
            }

            if (\array_key_exists($paramName, $systemArgs)) {
                $args[] = $systemArgs[$paramName];

                continue;
            }

            if (isset($details['c']) && \array_key_exists($details['c'], $systemArgs)) {
                $args[] = $systemArgs[$details['c']];

                continue;
            }

            if (isset($systemArgIndex) && \array_key_exists($systemArgIndex, $systemArgs)) {
                $currentSysArg = $systemArgs[$systemArgIndex];

                // If the parameter needs a specific class, ensure the system arg matches
                if (!isset($details['c'])) {
                    // It's a primitive parameter, safely consume the primitive system arg
                    $args[] = $currentSysArg;
                    $systemArgIndex++;

                    continue;
                }

                if (\is_object($currentSysArg) && $currentSysArg instanceof $details['c']) {
                    $args[] = $currentSysArg;
                    $systemArgIndex++;

                    continue;
                }
            }

            if (isset($details['c'])) {
                if (
                    $container::DEFAULT_PARAMETER_TAKES_PRECEDENCE_WHEN_AUTOWIRING
                    && \array_key_exists('d', $details)
                ) {
                    $args[] = $details['d'];

                    continue;
                }

                try {
                    $args[] = $container->make($details['c']);
                } catch (BindingResolutionException $ex) {
                    if (\array_key_exists('d', $details)) {
                        $args[] = $details['d'];

                        continue;
                    }

                    throw $ex;
                }

                continue;
            }

            if (
                !$container::DEFAULT_PARAMETER_TAKES_PRECEDENCE_WHEN_AUTOWIRING
                && \array_key_exists('d', $details)
            ) {
                $args[] = $details['d'];

                continue;
            }

            throw new BindingResolutionException(
                'Unresolvable dependency resolving [' . $paramName .
                '] in callable [' . $target[0] . '::' . $target[1] . ']. ' . $exception?->getMessage()
            );
        }

        if (!\is_callable($target)) {
            $target[0] = $container->make($target[0]);
        }

        return \call_user_func_array($target, $args);
    }
}
