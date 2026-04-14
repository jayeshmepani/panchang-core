#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Generate combined output JSON (festivals + eclipses + today's panchang).
 *
 * Usage: php scripts/panchang_raw_output.php
 * Output: stdout (redirect to file)
 *
 * Example: php scripts/panchang_raw_output.php > output.json
 */

use JayeshMepani\PanchangCore\Traits\CliBootstrap;

$baseDir = is_file(__DIR__ . '/../vendor/autoload.php') ? dirname(__DIR__) : __DIR__;
require $baseDir . '/vendor/autoload.php';

CliBootstrap::init($baseDir);

$timezone = 'Asia/Kolkata';
$latitude = 23.2472446;
$longitude = 69.668339;
$elevation = 0.0;
$city = 'Bhuj';
$country = 'IN';
$calendarType = config('panchang.defaults.calendar_type', 'amanta');

$panchangService = CliBootstrap::makePanchangService();
$outputGen = CliBootstrap::makeOutputGenerator($panchangService);

$result = $outputGen->generateAll(
    festivalYear: 2026,
    eclipseStartYear: 2026,
    eclipseEndYear: 2032,
    lat: $latitude,
    lon: $longitude,
    tz: $timezone,
    elevation: $elevation,
    calendarType: $calendarType,
);

$output = [
    'meta' => [
        'generated_at' => date('c'),
        'type' => 'combined_output',
        'calendar_type' => $calendarType,
        'location' => [
            'city' => $city,
            'country' => $country,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'timezone' => $timezone,
            'elevation' => $elevation,
        ],
    ],
    ...$result,
];

$json = json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($json === false) {
    fwrite(STDERR, 'JSON encoding failed: ' . json_last_error_msg() . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, $json . PHP_EOL);
