<?php

return [

    /*
    | Default Session Driver
    |
    | Supported: "file", "cookie", "database", "array"
    */

    'driver' => 'file',

    /*
    | Session Lifetime
    |
    */

    'lifetime' => 120,

    'expire_on_close' => false,

    /*
    | Session Encryption
    |
    */

    'encrypt' => false,

    /*
    | Session File Location
    |
    */

    'files' => storage_path('core/sessions'),

    /*
    | Session Database Connection
    |
    */

    'connection' => null,

    /*
    | Session Database Table
    |
    */

    'table' => 'sessions',

    /*
    | Session Sweeping Lottery
    |
    */

    'lottery' => [2, 100],

    /*
    | Session Cookie Name
    |
    */

    'cookie' => 'core_session',

    /*
    | Session Cookie Path
    |
    */

    'path' => '/',

    /*
    | Session Cookie Domain
    |
    */
    
    'domain' => '',

    /*
    | HTTP Access Only
    |
    */

    'http_only' => true,

    /*
    | HTTP Access Only
    |
    | Supported: "lax", "strict", "none", null
    */

    'same_site' => 'lax',

    /*
    | Partitioned Cookies
    |
    */

    'partitioned' => false,
];