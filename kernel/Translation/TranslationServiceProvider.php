<?php

namespace MacropaySolutions\Kernel\Translation;

use MacropaySolutions\Kernel\Contracts\Support\DeferrableProvider;
use MacropaySolutions\Kernel\Support\ServiceProvider;

class TranslationServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('translation.loader', [self::class, 'getTranslationLoader']);

        $this->app->singleton('translator', [self::class, 'getTranslator']);
    }

    public static function getTranslationLoader($app)
    {
        return new FileLoader($app->make('files'), [__DIR__ . '/lang', $app->make('path.lang')]);
    }

    public static function getTranslator($app)
    {
        $loader = $app->make('translation.loader');

        $trans = new Translator($loader, $app->getLocale());

        $trans->setFallback($app->getFallbackLocale());

        return $trans;
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['translator', 'translation.loader'];
    }
}
