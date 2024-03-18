<?php

return [

    /*
    | Application Name
    |
    */

    'name' => 'api-core',

    /*
    | Application Environment
    |
    */

    'env' => 'production',

    /*
    | Application Debug Mode
    |
    */

    'debug' => (bool) true,

    /*
    | Application Timezone
    |
    */

    'timezone' => 'UTC',

    /*
    | Encryption Key
    | key: base64:giFA9u65DFUF9XtqwfyAbLzybHyT0DH0YU8s7GZNnA4=
    |
    */

    'key' => 'api-core',

    'cipher' => 'AES-256-CBC',

    /*
    | Autoloaded Service Providers
    |
    */

    'providers' => [
        \Framework\Auth\AuthServiceProvider::class,
        \Framework\Database\DatabaseServiceProvider::class,
        \Framework\Filesystem\FilesystemServiceProvider::class,
        \Framework\Session\SessionServiceProvider::class,
    ],
];
