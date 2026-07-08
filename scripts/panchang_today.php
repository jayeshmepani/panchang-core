#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Generate today's complete panchang JSON.
 *
 * Usage: php scripts/panchang_today.php
 * Output: scripts/output/{calendar_type}/{locale}/today.json
 *
 * This data changes daily — run whenever you need current data.
 */

use JayeshMepani\PanchangCore\Support\DebugTrace;
use JayeshMepani\PanchangCore\Traits\CliBootstrap;

$baseDir = is_file(__DIR__ . '/../vendor/autoload.php') ? dirname(__DIR__) : __DIR__;
require $baseDir . '/vendor/autoload.php';
require __DIR__ . '/output_helpers.php';

CliBootstrap::init($baseDir);
DebugTrace::log('script.today', 'starting today panchang generation');

$timezone = 'Asia/Kolkata';
$latitude = 23.2472446;
$longitude = 69.668339;
$elevation = 0.0;
$city = 'Bhuj';
$country = 'IN';
$calendarType = panchang_script_calendar_type();
$locale = panchang_script_locale();
$outputDir = panchang_script_output_dir($baseDir, $calendarType, $locale);

$panchangService = CliBootstrap::makePanchangService();
$outputGen = CliBootstrap::makeOutputGenerator($panchangService);
DebugTrace::log('script.today', 'services constructed', [
    'calendar_type' => $calendarType,
    'locale' => $locale,
    'timezone' => $timezone,
    'latitude' => $latitude,
    'longitude' => $longitude,
]);

$result = $outputGen->generateTodayPanchang(
    lat: $latitude,
    lon: $longitude,
    tz: $timezone,
    elevation: $elevation,
    calendarType: $calendarType,
);
DebugTrace::log('script.today', 'generateTodayPanchang completed');

$todayDate = $result['todays_complete_details']['date'] ?? 'unknown';

$output = [
    'meta' => [
        'generated_at' => date('c'),
        'type' => 'today_panchang',
        'calendar_type' => $calendarType,
        'locale' => $locale,
        'location' => [
            'city' => $city,
            'country' => $country,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'timezone' => $timezone,
            'elevation' => $elevation,
        ],
    ],
    'today' => $result['todays_complete_details'],
    'muhurta_evaluation' => $result['muhurta_evaluation'],
];

$outputPath = $outputDir . DIRECTORY_SEPARATOR . 'today.json';

try {
    panchang_script_write_json($outputPath, $output);
} catch (RuntimeException $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}

DebugTrace::log('script.today', 'today.json written', ['date' => $todayDate, 'path' => $outputPath]);

echo "Written {$outputPath} — {$todayDate} for {$city}, {$country}." . PHP_EOL;
