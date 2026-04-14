#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Generate today's complete panchang JSON.
 *
 * Usage: php scripts/panchang_today.php
 * Output: today_panchang.json
 *
 * This data changes daily — run whenever you need current data.
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

$result = $outputGen->generateTodayPanchang(
    lat: $latitude,
    lon: $longitude,
    tz: $timezone,
    elevation: $elevation,
    calendarType: $calendarType,
);

$todayDate = $result['todays_complete_details']['date'] ?? 'unknown';

$output = [
    'meta' => [
        'generated_at' => date('c'),
        'type' => 'today_panchang',
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
    'today' => $result['todays_complete_details'],
    'muhurta_evaluation' => $result['muhurta_evaluation'],
];

$json = json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($json === false) {
    fwrite(STDERR, 'JSON encoding failed: ' . json_last_error_msg() . PHP_EOL);
    exit(1);
}

file_put_contents('today_panchang.json', $json . PHP_EOL);

echo "Written today_panchang.json — {$todayDate} for {$city}, {$country}." . PHP_EOL;
