#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Generate eclipses JSON for a range of years.
 *
 * Usage: php panchang_eclipses.php [start_year] [end_year]
 * Default: 2026-2032
 * Output: eclipses_{start_year}_{end_year}.json
 *
 * This data is static — run once for a multi-year range.
 */

$baseDir = is_file(__DIR__ . '/vendor/autoload.php') ? __DIR__ : dirname(__DIR__);
require $baseDir . '/vendor/autoload.php';

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use JayeshMepani\PanchangCore\Astronomy\EclipseService;
use SwissEph\FFI\SwissEphFFI;

// Minimal config setup for standalone usage
$configStore = ['panchang' => require $baseDir . '/config/panchang.php'];
if (class_exists(Container::class) && class_exists(Repository::class)) {
    $container = new Container;
    $container->instance('config', new Repository($configStore));
    Container::setInstance($container);
}

if (!function_exists('config')) {
    function config(array|string|null $key = null, mixed $default = null): mixed {
        global $configStore;
        if ($key === null) { return $configStore; }
        $segments = explode('.', $key);
        $value = $configStore;
        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) { return $default; }
            $value = $value[$segment];
        }
        return $value;
    }
}

$startYear = isset($argv[1]) ? (int) $argv[1] : 2026;
$endYear = isset($argv[2]) ? (int) $argv[2] : 2032;

$latitude = 23.2472446;
$longitude = 69.668339;
$timezone = 'Asia/Kolkata';

$sweph = new SwissEphFFI;
$eclipseService = new EclipseService($sweph);

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
        'location' => [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'timezone' => $timezone,
        ],
    ],
    'eclipses' => [
        'title' => "Eclipses {$startYear}-{$endYear} - All eclipses for " . ($endYear - $startYear + 1) . " years",
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

echo "Written {$filename} — " . count($eclipsesFlat) . " eclipses across " . ($endYear - $startYear + 1) . " years." . PHP_EOL;
