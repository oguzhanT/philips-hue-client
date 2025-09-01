<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Philips Hue Bridge Configuration
    |--------------------------------------------------------------------------
    |
    | Configure your Philips Hue Bridge connection settings here.
    | You can set multiple bridges if you have more than one.
    |
    */

    'default' => env('HUE_DEFAULT_BRIDGE', 'main'),

    'bridges' => [
        'main' => [
            'ip' => env('HUE_BRIDGE_IP'),
            'username' => env('HUE_USERNAME'),
            'options' => [
                'timeout' => env('HUE_TIMEOUT', 5),
                'verify' => env('HUE_VERIFY_SSL', false),
                'retry_attempts' => env('HUE_RETRY_ATTEMPTS', 3),
                'cache_enabled' => env('HUE_CACHE_ENABLED', true),
                'cache_type' => env('HUE_CACHE_TYPE', 'file'),
            ]
        ],

        // Example of secondary bridge
        'secondary' => [
            'ip' => env('HUE_BRIDGE_IP_2'),
            'username' => env('HUE_USERNAME_2'),
            'options' => [
                'timeout' => env('HUE_TIMEOUT', 5),
                'verify' => env('HUE_VERIFY_SSL', false),
                'retry_attempts' => env('HUE_RETRY_ATTEMPTS', 3),
                'cache_enabled' => env('HUE_CACHE_ENABLED', true),
                'cache_type' => env('HUE_CACHE_TYPE', 'file'),
            ]
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto Discovery
    |--------------------------------------------------------------------------
    |
    | Enable automatic bridge discovery if no bridge IP is configured.
    | This will scan your network for available Hue bridges.
    |
    */

    'auto_discovery' => env('HUE_AUTO_DISCOVERY', true),

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configure caching behavior for API responses.
    | Available drivers: file, redis, array, database
    |
    */

    'cache' => [
        'driver' => env('HUE_CACHE_DRIVER', 'file'),
        'prefix' => env('HUE_CACHE_PREFIX', 'hue_'),
        'ttl' => [
            'lights' => env('HUE_CACHE_LIGHTS_TTL', 10),
            'groups' => env('HUE_CACHE_GROUPS_TTL', 30),
            'scenes' => env('HUE_CACHE_SCENES_TTL', 60),
            'schedules' => env('HUE_CACHE_SCHEDULES_TTL', 60),
            'sensors' => env('HUE_CACHE_SENSORS_TTL', 5),
            'config' => env('HUE_CACHE_CONFIG_TTL', 300),
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | API Server Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for the built-in REST API server.
    |
    */

    'api' => [
        'enabled' => env('HUE_API_ENABLED', false),
        'port' => env('HUE_API_PORT', 8080),
        'rate_limit' => env('HUE_API_RATE_LIMIT', 100),
        'rate_window' => env('HUE_API_RATE_WINDOW', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Configure logging for Hue operations.
    |
    */

    'logging' => [
        'enabled' => env('HUE_LOGGING_ENABLED', true),
        'channel' => env('HUE_LOG_CHANNEL', 'single'),
        'level' => env('HUE_LOG_LEVEL', 'info'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Event Broadcasting
    |--------------------------------------------------------------------------
    |
    | Configure event broadcasting for real-time updates.
    |
    */

    'events' => [
        'enabled' => env('HUE_EVENTS_ENABLED', false),
        'broadcast' => env('HUE_EVENTS_BROADCAST', false),
        'channel_prefix' => env('HUE_EVENTS_CHANNEL_PREFIX', 'hue.'),
    ],
];