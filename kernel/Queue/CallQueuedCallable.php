<?php

namespace MacropaySolutions\Kernel\Queue;

use MacropaySolutions\Framework\Bus\PendingCallableDispatch;
use MacropaySolutions\Kernel\Bus\Batchable;
use MacropaySolutions\Kernel\Bus\Queueable;
use MacropaySolutions\Kernel\Container\BoundMethod;
use MacropaySolutions\Kernel\Contracts\Container\BindingResolutionException;
use MacropaySolutions\Kernel\Contracts\Container\Container;
use MacropaySolutions\Kernel\Contracts\Queue\Job;
use MacropaySolutions\Kernel\Contracts\Queue\ShouldBeUnique;
use MacropaySolutions\Kernel\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use MacropaySolutions\Kernel\Contracts\Queue\ShouldQueue;
use MacropaySolutions\Kernel\Database\Obvious\ModelNotFoundException;

/**
 * DO NOT ADD PROPERTY HOOKS IN THIS CLASS OR IN ITS TRAITS TO ALLOW OBJECT RECONSTRUCTION AFTER DESERIALIZATION!
 */
class CallQueuedCallable implements ShouldQueue
{
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
     * The number of times the job may be attempted.
     */
    public ?int $tries = null;

    /**
     * The number of seconds the job can run before timing out.
     */
    public ?int $timeout = null;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     */
    public ?int $maxExceptions = null;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public null|int|string|array $backoff = null;

    /**
     * The timestamp after which the job should no longer be attempted.
     */
    public ?int $retryUntil = null;

    /**
     * Indicates if the job should be marked as failed on timeout.
     */
    public bool $failOnTimeout = false;

    /**
     * Indicates if the job should be encrypted.
     */
    public bool $shouldBeEncrypted = false;

    /**
     * Create a new job instance.
     * @throws \InvalidArgumentException
     */
    public function __construct(array $callable)
    {
        $this->storableCallable = Queue::storableCallable($callable);
    }

    /**
     * Resolve the correct queueable wrapper based on the source's uniqueness.
     */
    public static function createFrom(object|string $source, array $storableCallable): static
    {
        $sourceIsObject = \is_object($source);
        $isUniqueUntilProcessing = $sourceIsObject
            ? $source instanceof ShouldBeUniqueUntilProcessing
            : \is_subclass_of($source, ShouldBeUniqueUntilProcessing::class);

        $isUnique = $sourceIsObject
            ? $source instanceof ShouldBeUnique
            : \is_subclass_of($source, ShouldBeUnique::class);

        $wrapperClass = match (true) {
            $isUniqueUntilProcessing => UniqueUntilProcessingCallQueuedCallable::class,
            $isUnique => UniqueCallQueuedCallable::class,
            default => static::class,
        };

        $callable = $wrapperClass::create($storableCallable);
        static::copyJobSettings($source, $callable);

        if ($sourceIsObject) {
            if (!isset($callable->messageGroup)) {
                $group = self::getMessageGroup($source);

                if ($group !== '') {
                    $callable->messageGroup = $group;
                }
            }

            if (!isset($callable->deduplicationId)) {
                $dedup = '';

                if (\method_exists($source, 'deduplicationId')) {
                    $dedup = (string)$source->deduplicationId();
                }

                if ($dedup !== '') {
                    $callable->deduplicationId = $dedup;
                }
            }
        }

        if ($callable instanceof ShouldBeUnique) {
            $resolvedSource = $sourceIsObject ? $source : null;

            if ($resolvedSource === null && \is_string($source) && \class_exists($source)) {
                try {
                    $resolvedSource = \app($source);
                } catch (\Throwable) {
                    // Fall back to class defaults if the target cannot be booted
                }
            }

            if ($resolvedSource instanceof ShouldBeUnique) {
                $uniqueId = match (true) {
                    \method_exists($resolvedSource, 'uniqueId') => $resolvedSource->uniqueId(),
                    \property_exists($resolvedSource, 'uniqueId') => $resolvedSource->uniqueId,
                    default => null,
                };

                if ($uniqueId !== null) {
                    $callable->setUniqueId((string) $uniqueId);
                }

                $uniqueFor = match (true) {
                    \method_exists($resolvedSource, 'uniqueFor') => $resolvedSource->uniqueFor(),
                    \property_exists($resolvedSource, 'uniqueFor') => $resolvedSource->uniqueFor,
                    default => null,
                };

                if ($uniqueFor !== null) {
                    $callable->setUniqueFor((int) $uniqueFor);
                }

                $cacheStore = $resolvedSource->uniqueCacheStore ?? null;

                if (\is_string($cacheStore)) {
                    $callable->setUniqueCacheStore($cacheStore);
                }
            }
        }

        return $callable;
    }

    /**
     * Copy the job settings and the failed() handler of the job source onto the queued callable.
     * Only public properties and public methods of the source are read.
     */
    protected static function copyJobSettings(object|string $source, self $callable): void
    {
        $sourceIsObject = \is_object($source);
        $sourceClass = $sourceIsObject ? $source::class : $source;

        if (!\class_exists($sourceClass)) {
            return;
        }

        $values = $sourceIsObject ? \get_object_vars($source) : \get_class_vars($sourceClass);

        foreach (['tries', 'timeout', 'maxExceptions', 'backoff', 'retryUntil'] as $setting) {
            if (isset($values[$setting])) {
                $callable->{$setting} = $values[$setting] instanceof \DateTimeInterface
                    ? $values[$setting]->getTimestamp()
                    : $values[$setting];
            }
        }

        if (true === ($values['failOnTimeout'] ?? false)) {
            $callable->failOnTimeout = true;
        }

        if (true === ($values['shouldBeEncrypted'] ?? false)) {
            $callable->shouldBeEncrypted = true;
        }

        if ($sourceIsObject) {
            static::copyJobSettingsFromMethods($source, $callable);
        }

        if (
            $callable->storableCallable[0] === $sourceClass
            && \in_array('failed', \get_class_methods($sourceClass), true)
        ) {
            $callable->onFailure([$sourceClass, 'failed', $callable->storableCallable[2] ?? []]);
        }
    }

    /**
     * Copy the job settings that the job source defines as methods.
     */
    protected static function copyJobSettingsFromMethods(object $source, self $callable): void
    {
        if (!isset($callable->tries) && \method_exists($source, 'tries')) {
            try {
                $tries = $source->tries();
            } catch (\Throwable) {
                $tries = null;
            }

            $callable->tries = $tries;
        }

        if (!isset($callable->backoff) && \method_exists($source, 'backoff')) {
            try {
                $backoff = $source->backoff();
            } catch (\Throwable) {
                $backoff = null;
            }

            $callable->backoff = $backoff;
        }

        if (!isset($callable->retryUntil) && \method_exists($source, 'retryUntil')) {
            try {
                $retryUntil = $source->retryUntil();
            } catch (\Throwable) {
                $retryUntil = null;
            }

            $callable->retryUntil = $retryUntil instanceof \DateTimeInterface
                ? $retryUntil->getTimestamp()
                : $retryUntil;
        }
    }

    protected static function getMessageGroup(object $resolvedSource): string
    {
        if (isset($resolvedSource->messageGroup) && (string)$resolvedSource->messageGroup !== '') {
            return (string)$resolvedSource->messageGroup;
        }

        if (\method_exists($resolvedSource, 'messageGroup')) {
            return (string)$resolvedSource->messageGroup();
        }

        return '';
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

        if (($this->job ?? null) instanceof Job) {
            $systemArgs['job'] = $systemArgs[Job::class] = $systemArgs[$this->job::class] = $this->job;
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

        $restorer = new SerializesModelsHelper();

        foreach ($userArgs as $key => $value) {
            $userArgs[$key] = $restorer->restorePropertyValue($value);
        }

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

        $restorer = new SerializesModelsHelper();

        if (isset($callback[2]) && \is_array($callback[2])) {
            try {
                foreach ($callback[2] as $key => $value) {
                    $callback[2][$key] = $restorer->restorePropertyValue($value);
                }
            } catch (ModelNotFoundException) {
                return;
            }
        }

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
