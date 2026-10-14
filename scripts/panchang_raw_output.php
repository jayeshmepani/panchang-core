#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Generate combined output JSON (festivals + eclipses + today's panchang).
 *
 * Usage: php scripts/panchang_raw_output.php [festival_year] [eclipse_start_year] [eclipse_end_year]
 * Output: scripts/output/{calendar_type}/{locale}/raw_output_{start}_{end}.json
 *
 * If stdout is piped or redirected, JSON is also emitted to stdout.
 */

use JayeshMepani\PanchangCore\Core\Localization;
use JayeshMepani\PanchangCore\Traits\CliBootstrap;

$baseDir = is_file(__DIR__ . '/../vendor/autoload.php') ? dirname(__DIR__) : __DIR__;
require $baseDir . '/vendor/autoload.php';
require __DIR__ . '/output_helpers.php';

CliBootstrap::init($baseDir);

$timezone = 'Asia/Kolkata';
$latitude = 23.2472446;
$longitude = 69.668339;
$elevation = 0.0;
$city = 'Bhuj';
$country = 'IN';
$calendarType = panchang_script_calendar_type();
$locale = panchang_script_locale();
$outputDir = panchang_script_output_dir($baseDir, $calendarType, $locale);
$festivalYear = isset($argv[1]) ? (int) $argv[1] : 2026;
$eclipseStartYear = isset($argv[2]) ? (int) $argv[2] : 2026;
$eclipseEndYear = isset($argv[3]) ? (int) $argv[3] : 2032;
$reuseGenerated = in_array('--reuse-generated', $argv, true);

if ($reuseGenerated) {
    $readJson = static function (string $path): array {
        if (! is_file($path)) {
            throw new RuntimeException('Required generated JSON is missing: ' . $path);
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Generated JSON is invalid: ' . $path);
        }

        return $decoded;
    };

    $festivalDocument = $readJson($outputDir . DIRECTORY_SEPARATOR . sprintf('festivals_%d.json', $festivalYear));
    $todayDocument = $readJson($outputDir . DIRECTORY_SEPARATOR . 'today.json');
    $eclipseDocument = null;
    foreach (glob($outputDir . DIRECTORY_SEPARATOR . 'eclipses_*.json') ?: [] as $eclipsePath) {
        $candidate = $readJson($eclipsePath);
        $from = (int) ($candidate['eclipses']['from_year'] ?? PHP_INT_MAX);
        $to = (int) ($candidate['eclipses']['to_year'] ?? PHP_INT_MIN);
        if ($from <= $eclipseStartYear && $to >= $eclipseEndYear) {
            $eclipseDocument = $candidate;
            break;
        }
    }
    if ($eclipseDocument === null) {
        throw new RuntimeException(sprintf(
            'No generated eclipse file covers %d-%d in %s.',
            $eclipseStartYear,
            $eclipseEndYear,
            $outputDir,
        ));
    }

    $festivalPayload = $festivalDocument['festivals'] ?? [];
    unset($festivalPayload['locale']);
    $sourceByYear = $eclipseDocument['eclipses']['by_year'] ?? [];
    $eclipsesByYear = [];
    foreach ($sourceByYear as $year => $events) {
        if ((int) $year >= $eclipseStartYear && (int) $year <= $eclipseEndYear) {
            $eclipsesByYear[(string) $year] = $events;
        }
    }
    $eclipsesFlat = [];
    foreach ($eclipsesByYear as $events) {
        array_push($eclipsesFlat, ...$events);
    }

    $result = [
        'festivals_' . $festivalYear => $festivalPayload,
        'eclipses_' . $eclipseStartYear . '_' . $eclipseEndYear => [
            'title' => sprintf(
                Localization::translate('String', 'Eclipses %d-%d - All eclipses for %d years'),
                $eclipseStartYear,
                $eclipseEndYear,
                $eclipseEndYear - $eclipseStartYear + 1,
            ),
            'from_year' => $eclipseStartYear,
            'to_year' => $eclipseEndYear,
            'total_eclipse_count' => count($eclipsesFlat),
            'by_year' => $eclipsesByYear,
            'flat' => $eclipsesFlat,
        ],
        'todays_complete_details' => $todayDocument['today'] ?? [],
        'muhurta_evaluation' => $todayDocument['muhurta_evaluation'] ?? [],
        'calendar_period_windows' => $todayDocument['calendar_period_windows'] ?? [],
    ];
} else {
    $panchangService = CliBootstrap::makePanchangService();
    $outputGen = CliBootstrap::makeOutputGenerator($panchangService);

    $result = $outputGen->generateAll(
        festivalYear: $festivalYear,
        eclipseStartYear: $eclipseStartYear,
        eclipseEndYear: $eclipseEndYear,
        lat: $latitude,
        lon: $longitude,
        tz: $timezone,
        elevation: $elevation,
        calendarType: $calendarType,
    );
}

$output = [
    'meta' => [
        'generated_at' => date('c'),
        'type' => 'combined_output',
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
    ...$result,
];

$filename = sprintf('raw_output_%d_%d.json', $eclipseStartYear, $eclipseEndYear);
$outputPath = $outputDir . DIRECTORY_SEPARATOR . $filename;

try {
    $json = panchang_script_write_json($outputPath, $output);
} catch (RuntimeException $runtimeException) {
    fwrite(STDERR, $runtimeException->getMessage() . PHP_EOL);
    exit(1);
}

if (panchang_stdout_is_interactive()) {
    fwrite(STDERR, 'Written ' . $outputPath . PHP_EOL);
} else {
    fwrite(STDOUT, $json);
}
