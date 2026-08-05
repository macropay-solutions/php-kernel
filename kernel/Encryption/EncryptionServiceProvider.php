<?php

namespace MacropaySolutions\Kernel\Encryption;

use MacropaySolutions\Kernel\Contracts\Support\DeferrableProvider;
use MacropaySolutions\Kernel\Support\ServiceProvider;
use MacropaySolutions\Kernel\Support\Str;

class EncryptionServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerEncrypter();
    }

    /**
     * Register the encrypter.
     *
     * @return void
     */
    protected function registerEncrypter()
    {
        $this->app->singleton('encrypter', function ($app) {
            $config = $app->make('config')->get('app');

            $map = [];

            foreach (($config['previous_keys_cipher_map'] ?? []) as $k => $c) {
                $map[$this->parseKey(['key' => $k])] = $c;
            }

            return new Encrypter($this->parseKey($config), $config['cipher'], $map);
        });
    }

    /**
     * Parse the encryption key.
     *
     * @param array $config
     * @return string
     */
    protected function parseKey(array $config)
    {
        if (Str::startsWith($key = $this->key($config), $prefix = 'base64:')) {
            $key = base64_decode(Str::after($key, $prefix));
        }

        return $key;
    }

    /**
     * Extract the encryption key from the given configuration.
     *
     * @param array $config
     * @return string
     *
     * @throws \MacropaySolutions\Kernel\Encryption\MissingAppKeyException
     */
    protected function key(array $config)
    {
        return tap($config['key'], function ($key) {
            if (empty($key)) {
                throw new MissingAppKeyException();
            }
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            'encrypter',
        ];
    }
}
