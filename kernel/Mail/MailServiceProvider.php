<?php

namespace MacropaySolutions\Kernel\Mail;

use MacropaySolutions\Kernel\Contracts\Support\DeferrableProvider;
use MacropaySolutions\Kernel\Support\ServiceProvider;

class MailServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerKernelMailer();
        $this->registerMarkdownRenderer();
    }

    /**
     * Register The MacropaySolutions Kernel mailer instance.
     *
     * @return void
     */
    protected function registerKernelMailer()
    {
        $this->app->singleton('mail.manager', [self::class, 'getMailManager']);

        $this->app->bind('mailer', [self::class, 'getMailer']);
    }

    public static function getMailManager($app)
    {
        return new MailManager($app);
    }

    public static function getMailer($app)
    {
        return $app->make('mail.manager')->mailer();
    }

    /**
     * Register the Markdown renderer instance.
     *
     * @return void
     */
    protected function registerMarkdownRenderer()
    {
        $this->app->singleton(Markdown::class, [self::class, 'getMarkdown']);
    }

    public static function getMarkdown($app)
    {
        $config = $app->make('config');

        return new Markdown($app->make('view'), [
            'theme' => $config->get('mail.markdown.theme', 'default'),
            'paths' => $config->get('mail.markdown.paths', []),
        ]);
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            'mail.manager',
            'mailer',
            Markdown::class,
        ];
    }
}
