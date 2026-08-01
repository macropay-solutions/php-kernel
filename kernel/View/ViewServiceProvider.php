<?php

namespace MacropaySolutions\Kernel\View;

use MacropaySolutions\Kernel\Container\Container;
use MacropaySolutions\Kernel\Contracts\Support\DeferrableProvider;
use MacropaySolutions\Kernel\Support\ServiceProvider;
use MacropaySolutions\Kernel\View\Compilers\TemplateCompiler;
use MacropaySolutions\Kernel\View\Engines\CompilerEngine;
use MacropaySolutions\Kernel\View\Engines\EngineResolver;
use MacropaySolutions\Kernel\View\Engines\FileEngine;
use MacropaySolutions\Kernel\View\Engines\PhpEngine;

class ViewServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerFactory();
        $this->registerViewFinder();
        $this->registerTemplateCompiler();
        $this->registerEngineResolver();

        $this->app->terminating(static function () {
            Component::flushCache();
        });
    }

    /**
     * Register the view environment.
     *
     * @return void
     */
    public function registerFactory()
    {
        $this->app->singleton('view', function ($app) {
            // Next we need to grab the engine resolver instance that will be used by the
            // environment. The resolver will be used by an environment to get each of
            // the various engine implementations such as plain PHP or Template engine.
            $resolver = $app['view.engine.resolver'];

            $finder = $app['view.finder'];

            $factory = $this->createFactory($resolver, $finder, $app['events']);

            // We will also set the container instance on this view environment since the
            // view composers may be classes registered in the container, which allows
            // for great testable, flexible composers for the application developer.
            $factory->setContainer($app);

            $factory->share('app', $app);

            $app->terminating(static function () {
                Component::forgetFactory();
            });

            return $factory;
        });
    }

    /**
     * Create a new Factory Instance.
     *
     * @param \MacropaySolutions\Kernel\View\Engines\EngineResolver $resolver
     * @param \MacropaySolutions\Kernel\View\ViewFinderInterface $finder
     * @param \MacropaySolutions\Kernel\Contracts\Events\Dispatcher $events
     * @return \MacropaySolutions\Kernel\View\Factory
     */
    protected function createFactory($resolver, $finder, $events)
    {
        return new Factory($resolver, $finder, $events);
    }

    /**
     * Register the view finder implementation.
     *
     * @return void
     */
    public function registerViewFinder()
    {
        $this->app->bind('view.finder', function ($app) {
            return new FileViewFinder($app['files'], $app['config']['view.paths']);
        });
    }

    /**
     * Register the Template compiler implementation.
     *
     * @return void
     */
    public function registerTemplateCompiler()
    {
        $this->app->singleton('template.compiler', function ($app) {
            return tap(
                new TemplateCompiler(
                    $app['files'],
                    $app['config']['view.compiled'],
                    $app['config']->get('view.relative_hash', false) ? $app->basePath() : '',
                    $app['config']->get('view.cache', true),
                    $app['config']->get('view.compiled_extension', 'php'),
                ),
                function ($template) {
                    $template->component('dynamic-component', DynamicComponent::class);
                }
            );
        });
    }

    /**
     * Register the engine resolver instance.
     *
     * @return void
     */
    public function registerEngineResolver()
    {
        $this->app->singleton('view.engine.resolver', function () {
            $resolver = new EngineResolver();

            // Next, we will register the various view engines with the resolver so that the
            // environment will resolve the engines needed for various views based on the
            // extension of view file. We call a method for each of the view's engines.
            foreach (['file', 'php', 'template'] as $engine) {
                $this->{'register' . ucfirst($engine) . 'Engine'}($resolver);
            }

            return $resolver;
        });
    }

    /**
     * Register the file engine implementation.
     *
     * @param \MacropaySolutions\Kernel\View\Engines\EngineResolver $resolver
     * @return void
     */
    public function registerFileEngine($resolver)
    {
        $resolver->register('file', function () {
            return new FileEngine(Container::getInstance()->make('files'));
        });
    }

    /**
     * Register the PHP engine implementation.
     *
     * @param \MacropaySolutions\Kernel\View\Engines\EngineResolver $resolver
     * @return void
     */
    public function registerPhpEngine($resolver)
    {
        $resolver->register('php', function () {
            return new PhpEngine(Container::getInstance()->make('files'));
        });
    }

    /**
     * Register the Template engine implementation.
     *
     * @param \MacropaySolutions\Kernel\View\Engines\EngineResolver $resolver
     * @return void
     */
    public function registerTemplateEngine($resolver)
    {
        $resolver->register('template', function () {
            $app = Container::getInstance();

            $compiler = new CompilerEngine(
                $app->make('template.compiler'),
                $app->make('files'),
            );

            $app->terminating(static function () use ($compiler) {
                $compiler->forgetCompiledOrNotExpired();
            });

            return $compiler;
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            'view',
            'view.finder',
            'template.compiler',
            'view.engine.resolver',
        ];
    }
}
