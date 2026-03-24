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
    | Default options for panchanga calculations.
    |
    */

    'defaults' => [
        'measurement_system' => 'indian_metric',
        'date_time_format' => 'indian_12h',
        'time_notation' => '12h',
        'coordinate_format' => 'decimal',
        'angle_unit' => 'degree',
        'duration_format' => 'mixed',
        'number_precision' => 9,
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
        'supported_regions' => ['North', 'South', 'Bengal', 'Maharashtra', 'Tamil'],
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
