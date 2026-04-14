#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Generate festivals JSON for a given year.
 *
 * Usage: php scripts/panchang_festivals.php [year]
 * Default: current year
 * Output: festivals_{year}.json
 *
 * This data is static — run once per year.
 */

use JayeshMepani\PanchangCore\Traits\CliBootstrap;

$baseDir = is_file(__DIR__ . '/../vendor/autoload.php') ? dirname(__DIR__) : __DIR__;
require $baseDir . '/vendor/autoload.php';

CliBootstrap::init($baseDir);

$festivalYear = isset($argv[1]) ? (int) $argv[1] : (int) date('Y');
$timezone = 'Asia/Kolkata';
$latitude = 23.2472446;
$longitude = 69.668339;
$elevation = 0.0;
$calendarType = config('panchang.defaults.calendar_type', 'amanta');

$panchangService = CliBootstrap::makePanchangService();
$outputGen = CliBootstrap::makeOutputGenerator($panchangService);

echo "Building festivals for {$festivalYear}..." . PHP_EOL;

$result = $outputGen->generateFestivals(
    year: $festivalYear,
    lat: $latitude,
    lon: $longitude,
    tz: $timezone,
    elevation: $elevation,
    calendarType: $calendarType,
);

$output = [
    'meta' => [
        'generated_at' => date('c'),
        'type' => 'festivals',
        'year' => $festivalYear,
        'calendar_type' => $calendarType,
        'location' => [
            'city' => 'Bhuj',
            'country' => 'IN',
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

$filename = "festivals_{$festivalYear}.json";
file_put_contents($filename, $json . PHP_EOL);

$festData = $result['festivals'];
echo "Written {$filename} — {$festData['festival_day_count']} festival days, {$festData['festival_entry_count']} entries." . PHP_EOL;
