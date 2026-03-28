<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Swiss Ephemeris Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the Swiss Ephemeris library path and settings.
    |
    */

    'ephe_path' => env('PANCHANG_EPHE_PATH', __DIR__ . '/../ephe'),

    /*
    |--------------------------------------------------------------------------
    | Ayanamsa System
    |--------------------------------------------------------------------------
    |
    | Supported: LAHIRI, RAMAN, KRISHNAMURTI
    | Default: LAHIRI (most widely used in Vedic astrology)
    |
    */

    'ayanamsa' => env('PANCHANG_AYANAMSA', 'LAHIRI'),

    /*
    |--------------------------------------------------------------------------
    | Default Calculation Options
    |--------------------------------------------------------------------------
    |
    | Default options for panchanga calculations and representation formats.
    |
    */

    'defaults' => [
        // 'indian_metric', 'western'
        'measurement_system' => 'indian_metric',

        // 'indian_12h', 'indian_24h', 'iso8601'
        'date_time_format' => 'indian_12h',

        // '12h', '24h'
        'time_notation' => '12h',

        // 'decimal', 'dms' (degrees, minutes, seconds)
        'coordinate_format' => 'decimal',

        // 'degree', 'dms'
        'angle_unit' => 'degree',

        // 'mixed' (e.g. '1h 30m 0s'), 'minutes' (90), 'seconds' (5400), 'hours' (1.5)
        'duration_format' => 'mixed',
    ],

    /*
    |--------------------------------------------------------------------------
    | Festival Calculation Settings
    |--------------------------------------------------------------------------
    |
    | Configure festival calculation behavior.
    |
    */

    'festivals' => [
        'default_tradition' => 'Smarta',
        'default_region' => 'North',
        'supported_traditions' => ['Smarta', 'Vaishnava'],
        'supported_regions' => ['North', 'South', 'Bengal', 'Maharashtra', 'Tamil', 'Gujarat'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    |
    | Configure caching for expensive calculations.
    |
    */

    'cache' => [
        'enabled' => env('PANCHANG_CACHE_ENABLED', true),
        'ttl' => env('PANCHANG_CACHE_TTL', 86400), // 24 hours
        'prefix' => env('PANCHANG_CACHE_PREFIX', 'panchang_'),
    ],
];
