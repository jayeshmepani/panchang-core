#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Generate monthly calendar grid JSON.
 *
 * Usage: php scripts/panchang_month_output.php [year] [month]
 * Default: current year/month
 * Output: stdout (redirect to file)
 *
 * Example: php scripts/panchang_month_output.php 2026 4 > month_2026_04.json
 */

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Traits\CliBootstrap;

$baseDir = is_file(__DIR__ . '/../vendor/autoload.php') ? dirname(__DIR__) : __DIR__;
require $baseDir . '/vendor/autoload.php';

CliBootstrap::init($baseDir);

$timezone = 'Asia/Kolkata';
$latitude = 23.2472446;
$longitude = 69.668339;
$elevation = 0.0;
$calendarType = config('panchang.defaults.calendar_type', 'purnimanta');

$year = isset($argv[1]) ? (int) $argv[1] : (int) date('Y');
$month = isset($argv[2]) ? (int) $argv[2] : (int) date('m');

$fixedRefDate = CarbonImmutable::create($year, $month, 1, 0, 0, 0, $timezone);

$panchangService = CliBootstrap::makePanchangService();

$calendar = $panchangService->getMonthCalendar(
    year: $year,
    month: $month,
    lat: $latitude,
    lon: $longitude,
    tz: $timezone,
    elevation: $elevation,
    calculationAt: $fixedRefDate,
    calendarType: $calendarType,
);

$output = [
    'meta' => [
        'generated_at' => date('c'),
        'year' => $year,
        'month' => $month,
        'calendar_type' => $calendarType,
        'location' => [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'timezone' => $timezone,
        ],
    ],
    'calendar' => $calendar,
];

$json = json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if ($json === false) {
    fwrite(STDERR, 'JSON encoding failed: ' . json_last_error_msg() . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, $json . PHP_EOL);
