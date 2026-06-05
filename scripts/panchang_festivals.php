#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Generate festivals JSON for a given year.
 *
 * Usage: php scripts/panchang_festivals.php [year] [all|festivals|vrats]
 * Default: current year
 * Output: festivals_{year}.json (by_date only)
 *
 * This data is static — run once per year.
 */

use JayeshMepani\PanchangCore\Core\Localization;
use JayeshMepani\PanchangCore\Festivals\FestivalService;
use JayeshMepani\PanchangCore\Traits\CliBootstrap;

$baseDir = is_file(__DIR__ . '/../vendor/autoload.php') ? dirname(__DIR__) : __DIR__;
require $baseDir . '/vendor/autoload.php';

CliBootstrap::init($baseDir);

$festivalYear = isset($argv[1]) ? (int) $argv[1] : (int) date('Y');
$scope = strtolower((string) ($argv[2] ?? 'all'));
$timezone = 'Asia/Kolkata';
$latitude = 23.2472446;
$longitude = 69.668339;
$elevation = 0.0;
$calendarType = config('panchang.defaults.calendar_type', 'amanta');

$panchangService = CliBootstrap::makePanchangService();
$outputGen = CliBootstrap::makeOutputGenerator($panchangService);

if (!in_array($scope, ['all', 'festivals', 'vrats'], true)) {
    fwrite(STDERR, "Unknown scope: {$scope}. Allowed: all, festivals, vrats" . PHP_EOL);
    exit(1);
}

echo "Building {$scope} output for {$festivalYear}..." . PHP_EOL;

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
                'festival_day_count' => $calendar['festival_day_count'],
                'festival_entry_count' => $calendar['festival_entry_count'],
                'total_festivals' => $calendar['festival_entry_count'],
                'by_date' => $calendar['by_date'],
            ],
        ],
        'vrats' => $calendar,
        default => [
            'festivals' => [
                'title' => sprintf(Localization::translate('String', 'Festivals %d - All festivals for the entire year'), $festivalYear),
                'year' => $festivalYear,
                'calendar_type' => $calendarType,
                'festival_day_count' => $calendar['festival_day_count'],
                'festival_entry_count' => $calendar['festival_entry_count'],
                'total_festivals' => FestivalService::getFestivalCount(),
                'by_date' => $calendar['by_date'],
            ],
        ],
    },
];

$json = json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($json === false) {
    fwrite(STDERR, 'JSON encoding failed: ' . json_last_error_msg() . PHP_EOL);
    exit(1);
}

$filename = match ($scope) {
    'festivals' => "festivals_only_{$festivalYear}.json",
    'vrats' => "vrats_{$festivalYear}.json",
    default => "festivals_{$festivalYear}.json",
};
file_put_contents($filename, $json . PHP_EOL);

$payload = $scope === 'vrats' ? $output['vrats'] : $output['festivals'];
$dayCount = $scope === 'vrats' ? $payload['vrat_day_count'] : $payload['festival_day_count'];
$entryCount = $scope === 'vrats' ? $payload['vrat_entry_count'] : $payload['festival_entry_count'];
echo "Written {$filename} — {$dayCount} days, {$entryCount} entries." . PHP_EOL;
