#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Generate eclipses JSON for a range of years.
 *
 * Usage: php scripts/panchang_eclipses.php [start_year] [end_year]
 * Default: 2018-2025
 * Output: scripts/output/{calendar_type}/{locale}/eclipses_{start_year}_{end_year}.json
 *
 * This data is static — run once for a multi-year range.
 */

use JayeshMepani\PanchangCore\Core\Localization;
use JayeshMepani\PanchangCore\Traits\CliBootstrap;

$baseDir = is_file(__DIR__ . '/../vendor/autoload.php') ? dirname(__DIR__) : __DIR__;
require $baseDir . '/vendor/autoload.php';
require __DIR__ . '/output_helpers.php';

CliBootstrap::init($baseDir);

$startYear = isset($argv[1]) ? (int) $argv[1] : 2018;
$endYear = isset($argv[2]) ? (int) $argv[2] : 2025;
$timezone = 'Asia/Kolkata';
$latitude = 23.2472446;
$longitude = 69.668339;
$calendarType = panchang_script_calendar_type();
$locale = panchang_script_locale();
$outputDir = panchang_script_output_dir($baseDir, $calendarType, $locale);

$eclipseService = CliBootstrap::makeEclipseService();

$eclipsesByYear = [];
$eclipsesFlat = [];

echo "Building eclipses for {$startYear}-{$endYear}..." . PHP_EOL;

for ($year = $startYear; $year <= $endYear; $year++) {
    $events = $eclipseService->getEclipsesForYear($year, $latitude, $longitude, $timezone);
    $eclipsesByYear[(string) $year] = $events;
    foreach ($events as $event) {
        $eclipsesFlat[] = $event;
    }
}

$output = [
    'meta' => [
        'generated_at' => date('c'),
        'type' => 'eclipses',
        'from_year' => $startYear,
        'to_year' => $endYear,
        'calendar_type' => $calendarType,
        'locale' => $locale,
        'location' => [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'timezone' => $timezone,
        ],
    ],
    'eclipses' => [
        'title' => sprintf(
            Localization::translate('String', 'Eclipses %d-%d - All eclipses for %d years'),
            $startYear,
            $endYear,
            $endYear - $startYear + 1
        ),
        'from_year' => $startYear,
        'to_year' => $endYear,
        'calendar_type' => $calendarType,
        'locale' => $locale,
        'total_eclipse_count' => count($eclipsesFlat),
        'by_year' => $eclipsesByYear,
        'flat' => $eclipsesFlat,
    ],
];

$filename = "eclipses_{$startYear}_{$endYear}.json";
$outputPath = $outputDir . DIRECTORY_SEPARATOR . $filename;

try {
    panchang_script_write_json($outputPath, $output);
} catch (RuntimeException $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}

echo "Written {$outputPath} — " . count($eclipsesFlat) . ' eclipses across ' . ($endYear - $startYear + 1) . ' years.' . PHP_EOL;
