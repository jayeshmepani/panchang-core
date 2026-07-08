#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Generate monthly calendar grid JSON.
 *
 * Usage: php scripts/panchang_month_output.php [year] [month]
 * Default: current month
 * Output: scripts/output/{calendar_type}/{locale}/month_{year}_{month}.json
 *
 * If stdout is piped or redirected, JSON is also emitted to stdout.
 */

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Traits\CliBootstrap;

$baseDir = is_file(__DIR__ . '/../vendor/autoload.php') ? dirname(__DIR__) : __DIR__;
require $baseDir . '/vendor/autoload.php';
require __DIR__ . '/output_helpers.php';

CliBootstrap::init($baseDir);

$timezone = 'Asia/Kolkata';
$latitude = 23.2472446;
$longitude = 69.668339;
$elevation = 0.0;
$calendarType = panchang_script_calendar_type();
$locale = panchang_script_locale();
$outputDir = panchang_script_output_dir($baseDir, $calendarType, $locale);

$year = isset($argv[1]) ? (int) $argv[1] : (int) CarbonImmutable::now($timezone)->format('Y');
$month = isset($argv[2]) ? (int) $argv[2] : (int) CarbonImmutable::now($timezone)->format('m');

$fixedRefDate = CarbonImmutable::create($year, $month, 1, 0, 0, 0, $timezone);

$panchangService = CliBootstrap::makePanchangService();

fwrite(STDERR, sprintf("Building month output for %04d-%02d...\n", $year, $month));

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
        'locale' => $locale,
        'location' => [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'timezone' => $timezone,
        ],
    ],
    'calendar' => $calendar,
];

$filename = sprintf('month_%04d_%02d.json', $year, $month);
$outputPath = $outputDir . DIRECTORY_SEPARATOR . $filename;

try {
    $json = panchang_script_write_json($outputPath, $output);
} catch (RuntimeException $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}

if (panchang_stdout_is_interactive()) {
    fwrite(STDERR, "Written {$outputPath}" . PHP_EOL);
} else {
    fwrite(STDOUT, $json);
}
