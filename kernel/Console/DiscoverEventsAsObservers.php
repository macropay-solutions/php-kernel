<?php

namespace MacropaySolutions\Kernel\Console;

use MacropaySolutions\Kernel\Database\Obvious\Model;
use MacropaySolutions\Kernel\Support\Reflector;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use Symfony\Component\Finder\Finder;

class DiscoverEventsAsObservers extends DiscoverEvents
{
    /**
     * @inheritdoc
     */
    public static function within($listenerPath, $basePath)
    {
        return static::getListenerEvents(
            Finder::create()->files()->in($listenerPath),
            $basePath
        );
    }

    /**
     * Get all the listeners and their corresponding events.
     *
     * @param iterable $listeners
     * @param string $basePath
     * @return array ["obvious.{$event}: {$modelFQN}" => ["{$observerFQN}@{$event}", ], ]
     */
    protected static function getListenerEvents($listeners, $basePath)
    {
        $observerEvents = [];

        foreach ($listeners as $listener) {
            try {
                $listener = new ReflectionClass(
                    static::classFromFile($listener, $basePath)
                );
            } catch (ReflectionException) {
                continue;
            }

            if (!$listener->isInstantiable()) {
                continue;
            }

            foreach ($listener->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $reflectionParameters = $method->getParameters();
                $firstReflectionParameter = \reset($reflectionParameters);

                if (!$firstReflectionParameter instanceof \ReflectionParameter) {
                    continue;
                }

                $parameterClassNames = Reflector::getParameterClassNames($firstReflectionParameter);

                if (
                    \is_string($modelFqn = \reset($parameterClassNames))
                    && \class_exists($modelFqn)
                    && \is_a($modelFqn, Model::class, true)
                    && \in_array($method->name, (new $modelFqn())->getObservableEvents(), true)
                ) {
                    $observerEvents['obvious.' . $method->name . ': ' . $modelFqn][] =
                        $listener->name . '@' . $method->name;
                }
            }
        }

        foreach ($observerEvents as $event => $observers) {
            $observerEvents[$event] = arrayUniqueSortRegular($observers);
        }

        return $observerEvents;
    }
}
