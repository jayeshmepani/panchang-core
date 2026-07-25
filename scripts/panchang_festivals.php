#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Generate festivals JSON for a given year.
 *
 * Usage: php scripts/panchang_festivals.php [year] [all|festivals|vrats]
 * Default: current year
 * Output: scripts/output/{calendar_type}/{locale}/*.json
 *
 * This data is static — run once per year.
 */

use JayeshMepani\PanchangCore\Core\Localization;
use JayeshMepani\PanchangCore\Festivals\FestivalService;
use JayeshMepani\PanchangCore\Traits\CliBootstrap;

$baseDir = is_file(__DIR__ . '/../vendor/autoload.php') ? dirname(__DIR__) : __DIR__;
require $baseDir . '/vendor/autoload.php';
require __DIR__ . '/output_helpers.php';

CliBootstrap::init($baseDir);

$festivalYear = isset($argv[1]) ? (int) $argv[1] : (int) date('Y');
$scope = strtolower((string) ($argv[2] ?? 'all'));
$timezone = 'Asia/Kolkata';
$latitude = 23.2472446;
$longitude = 69.668339;
$elevation = 0.0;
$calendarType = panchang_script_calendar_type();
$locale = panchang_script_locale();
$outputDir = panchang_script_output_dir($baseDir, $calendarType, $locale);

$panchangService = CliBootstrap::makePanchangService();
$outputGen = CliBootstrap::makeOutputGenerator($panchangService);

if (! in_array($scope, ['all', 'festivals', 'vrats'], true)) {
    fwrite(STDERR, sprintf('Unknown scope: %s. Allowed: all, festivals, vrats', $scope) . PHP_EOL);
    exit(1);
}

echo sprintf('Building %s output for %d...', $scope, $festivalYear) . PHP_EOL;

$calendar = match ($scope) {
    'festivals' => $outputGen->generateFestivalsOnlySelected(
        year: $festivalYear,
        lat: $latitude,
        lon: $longitude,
        tz: $timezone,
        sections: ['by_date', 'festival_day_count', 'festival_entry_count'],
        elevation: $elevation,
        calendarType: $calendarType,
    ),
    'vrats' => $outputGen->generateVratsByDateCompact(
        year: $festivalYear,
        lat: $latitude,
        lon: $longitude,
        tz: $timezone,
        elevation: $elevation,
        calendarType: $calendarType,
    ),
    default => $outputGen->generateFestivalsSelected(
        year: $festivalYear,
        lat: $latitude,
        lon: $longitude,
        tz: $timezone,
        sections: ['by_date', 'festival_day_count', 'festival_entry_count'],
        elevation: $elevation,
        calendarType: $calendarType,
    ),
};

// Catalog totals are year/calendar-independent (definitions always count, even if not observed).
$catalogFestivalCount = FestivalService::getCatalogFestivalCount();
$catalogVratCount = FestivalService::getCatalogVratCount();

$output = [
    'meta' => [
        'generated_at' => date('c'),
        'type' => match ($scope) {
            'festivals' => 'festivals_only',
            'vrats' => 'vrats',
            default => 'festivals',
        },
        'year' => $festivalYear,
        'calendar_type' => $calendarType,
        'locale' => $locale,
        'location' => [
            'city' => 'Bhuj',
            'country' => 'IN',
            'latitude' => $latitude,
            'longitude' => $longitude,
            'timezone' => $timezone,
            'elevation' => $elevation,
        ],
    ],
    ...match ($scope) {
        'festivals' => [
            'festivals' => [
                'title' => sprintf(Localization::translate('String', 'Festivals %d - Named festivals excluding vrat observances'), $festivalYear),
                'year' => $festivalYear,
                'calendar_type' => $calendarType,
                'locale' => $locale,
                'festival_day_count' => $calendar['festival_day_count'],
                'festival_entry_count' => $calendar['festival_entry_count'],
                'total_festivals' => $catalogFestivalCount,
                'by_date' => $calendar['by_date'],
            ],
        ],
        'vrats' => $calendar,
        default => [
            'festivals' => [
                'title' => sprintf(Localization::translate('String', 'Festivals %d - All festivals for the entire year'), $festivalYear),
                'year' => $festivalYear,
                'calendar_type' => $calendarType,
                'locale' => $locale,
                'festival_day_count' => $calendar['festival_day_count'],
                'festival_entry_count' => $calendar['festival_entry_count'],
                'total_festivals' => $catalogFestivalCount,
                'total_vrats' => $catalogVratCount,
                'by_date' => $calendar['by_date'],
            ],
        ],
    },
];

$filename = match ($scope) {
    'festivals' => sprintf('festivals_only_%d.json', $festivalYear),
    'vrats' => sprintf('vrats_%d.json', $festivalYear),
    default => sprintf('festivals_%d.json', $festivalYear),
};
$outputPath = $outputDir . DIRECTORY_SEPARATOR . $filename;

try {
    panchang_script_write_json($outputPath, $output);
} catch (RuntimeException $runtimeException) {
    fwrite(STDERR, $runtimeException->getMessage() . PHP_EOL);
    exit(1);
}

$payload = $scope === 'vrats' ? $output['vrats'] : $output['festivals'];
$dayCount = $scope === 'vrats' ? $payload['vrat_day_count'] : $payload['festival_day_count'];
$entryCount = $scope === 'vrats' ? $payload['vrat_entry_count'] : $payload['festival_entry_count'];
echo sprintf('Written %s — %s days, %s entries.', $outputPath, $dayCount, $entryCount) . PHP_EOL;
