<?php

namespace Illuminate\Encryption;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class EncryptionServiceProvider extends ServiceProvider
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
     * @throws \Illuminate\Encryption\MissingAppKeyException
     */
    protected function key(array $config)
    {
        return tap($config['key'], function ($key) {
            if (empty($key)) {
                throw new MissingAppKeyException();
            }
        });
    }
}
