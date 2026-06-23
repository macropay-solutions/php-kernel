<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application. This value is used when the
    | framework needs to place the application's name in a notification or
    | any other location as required by the application or its packages.
    |
    */

    'name' => env('APP_NAME', 'Framework'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool)env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Run command line tool. You should set this to the root of
    | your application so that it is used when running Run tasks.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. We have gone
    | ahead and set this to a sensible default for you out of the box.
    |
    */

    'timezone' => 'UTC',

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by the translation service provider. You are free to set this value
    | to any of the locales which will be supported by the application.
    |
    */

    'locale' => env('APP_LOCALE', 'en'),

    /*
    |--------------------------------------------------------------------------
    | Application Fallback Locale
    |--------------------------------------------------------------------------
    |
    | The fallback locale determines the locale to use when the current one
    | is not available. You may change the value to correspond to any of
    | the language folders that are provided through your application.
    |
    */

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is used by the Illuminate encrypter service and should be set
    | to a random, 32 character string, otherwise these encrypted strings
    | will not be safe. Please do this before deploying an application!
    |
    */

    'key' => env('APP_KEY'),

    'cipher' => 'AES-256-CBC',

    // Previous encryption keys to cypher map to be used for decryption...
    'previous_keys_cipher_map' => (array)\json_decode((string)\env('APP_PREVIOUS_KEYS_CIPHERS_MAP_JSON', '[]'), true),

    'debug_blacklist' => [
        '_COOKIE' => \array_keys($_COOKIE),
        '_SERVER' => \array_keys($_SERVER),
        '_ENV' => \array_keys($_ENV),
    ],

    /**
     * run autowiring:cache source paths for public methods (except __construct which is implicitly handled)
     * The CallQueuedHandler, controllers, middlewares, built-in commands, service providers, macroable classes
     *  + other classes resolved from Container during the autowiring:cache command execution are handled automatically
     * 'path' can be a single class FQN or a directory path
     * This allows you to add autowiring to any constructor/method from a class if you want
     *   when you resolve that class from the container or use BoundMethod::call to call that method.
     * Use '*' for all public methods.
     * Packages can auto add to this config via their composer.json:
     * {
     *  'extra': {
     *   'php-framework': {
     *    'autowiring': [
     *     {
     *      'path': 'src/ExampleFolder',
     *      'methods': []
     *     },
     *     {
     *      'path': '\\Vendor\\ExampleClass',
     *      'methods': []
     *     }
     *    ]
     *   }
     *  }
     * }
     */
    'autowiring' => [
        [
            'path' => \app()->path() . DIRECTORY_SEPARATOR . 'Console' . DIRECTORY_SEPARATOR . 'Commands',
            'methods' => ['handle', '__invoke'],
        ],
        [
            'path' => \app()->path() . DIRECTORY_SEPARATOR . 'Jobs',
            'methods' => ['handle', '__invoke'],
        ],
        [
            'path' => \app()->path() . DIRECTORY_SEPARATOR . 'Http' . DIRECTORY_SEPARATOR . 'Requests',
            'methods' => ['validator', 'authorize', 'after', 'rules'],
        ],
        [
            'path' => \app()->path() . DIRECTORY_SEPARATOR . 'Listeners',
            'methods' => [],
        ],
        [
            'path' => \app()->path() . DIRECTORY_SEPARATOR . 'CallablesAsArray',
            'methods' => ['*'],
        ],
    ],

    /**
     * Limit of extra memory used to detect circular dependencies when resolving an abstract from container (bytes)
     * use 0 to disable or 10485760 for 10 MB
     */
    'circular_dependency_memory_limit' => 0,

];
