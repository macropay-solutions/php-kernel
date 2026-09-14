<?php

namespace MacropaySolutions\Kernel\Filesystem;

use MacropaySolutions\Kernel\Contracts\Support\DeferrableProvider;
use MacropaySolutions\Kernel\Support\ServiceProvider;

class FilesystemServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerNativeFilesystem();

        $this->registerFlysystem();
    }

    /**
     * Register the native filesystem implementation.
     *
     * @return void
     */
    protected function registerNativeFilesystem()
    {
        $this->app->singleton('files', [self::class, 'getFiles']);
    }

    public static function getFiles()
    {
        return new Filesystem();
    }

    /**
     * Register the driver based filesystem.
     *
     * @return void
     */
    protected function registerFlysystem()
    {
        $this->registerManager();

        $this->app->singleton('filesystem.disk', [self::class, 'getFilesystemDisk']);

        $this->app->singleton('filesystem.cloud', [self::class, 'getFilesystemCloud']);
    }

    public static function getFilesystemDisk($app)
    {
        return $app->make('filesystem')->disk(self::getDefaultDriver($app));
    }

    public static function getFilesystemCloud($app)
    {
        return $app->make('filesystem')->disk(self::getCloudDriver($app));
    }

    /**
     * Register the filesystem manager.
     *
     * @return void
     */
    protected function registerManager()
    {
        $this->app->singleton('filesystem', [self::class, 'getFilesystemManager']);
    }

    public static function getFilesystemManager($app)
    {
        return new FilesystemManager($app);
    }

    /**
     * Get the default file driver.
     */
    protected static function getDefaultDriver($app): string
    {
        return $app->make('config')->get('filesystems.default');
    }

    /**
     * Get the default cloud based file driver.
     */
    protected static function getCloudDriver($app): string
    {
        return $app->make('config')->get('filesystems.cloud');
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            'files',
            'filesystem',
            'filesystem.disk',
            'filesystem.cloud',
        ];
    }
}
