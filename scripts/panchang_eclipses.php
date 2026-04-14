#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Generate eclipses JSON for a range of years.
 *
 * Usage: php scripts/panchang_eclipses.php [start_year] [end_year]
 * Default: 2026-2032
 * Output: eclipses_{start_year}_{end_year}.json
 *
 * This data is static — run once for a multi-year range.
 */

use JayeshMepani\PanchangCore\Core\Localization;
use JayeshMepani\PanchangCore\Traits\CliBootstrap;

$baseDir = is_file(__DIR__ . '/../vendor/autoload.php') ? dirname(__DIR__) : __DIR__;
require $baseDir . '/vendor/autoload.php';

CliBootstrap::init($baseDir);

$startYear = isset($argv[1]) ? (int) $argv[1] : 2026;
$endYear = isset($argv[2]) ? (int) $argv[2] : 2032;
$timezone = 'Asia/Kolkata';
$latitude = 23.2472446;
$longitude = 69.668339;

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
        'location' => ['latitude' => $latitude, 'longitude' => $longitude, 'timezone' => $timezone],
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
        'total_eclipse_count' => count($eclipsesFlat),
        'by_year' => $eclipsesByYear,
        'flat' => $eclipsesFlat,
    ],
];

$json = json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($json === false) {
    fwrite(STDERR, 'JSON encoding failed: ' . json_last_error_msg() . PHP_EOL);
    exit(1);
}

$filename = "eclipses_{$startYear}_{$endYear}.json";
file_put_contents($filename, $json . PHP_EOL);

echo "Written {$filename} — " . count($eclipsesFlat) . ' eclipses across ' . ($endYear - $startYear + 1) . ' years.' . PHP_EOL;
