#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Generate festivals JSON for a given year.
 *
 * Usage: php panchang_festivals.php [year]
 * Default: current year
 * Output: festivals_{year}.json
 *
 * This data is static — run once per year.
 */
$baseDir = is_file(__DIR__ . '/vendor/autoload.php') ? __DIR__ : dirname(__DIR__);
require $baseDir . '/vendor/autoload.php';

use Carbon\CarbonImmutable;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use JayeshMepani\PanchangCore\Astronomy\AstronomyService;
use JayeshMepani\PanchangCore\Astronomy\SunService;
use JayeshMepani\PanchangCore\Festivals\FestivalFamilyOrchestrator;
use JayeshMepani\PanchangCore\Festivals\FestivalRuleEngine;
use JayeshMepani\PanchangCore\Festivals\FestivalService;
use JayeshMepani\PanchangCore\Festivals\Utils\BhadraEngine;
use JayeshMepani\PanchangCore\Panchanga\MuhurtaService;
use JayeshMepani\PanchangCore\Panchanga\PanchangaEngine;
use JayeshMepani\PanchangCore\Panchanga\PanchangService;
use SwissEph\FFI\SwissEphFFI;

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($value === false) {
            return $default;
        }
        if (is_string($value)) {
            $trimmed = trim($value);
            $lower = strtolower($trimmed);
            if ($lower === 'true' || $lower === '(true)') { return true; }
            if ($lower === 'false' || $lower === '(false)') { return false; }
            if ($lower === 'null' || $lower === '(null)') { return null; }
            if ($lower === 'empty' || $lower === '(empty)') { return ''; }
            return $trimmed;
        }
        return $value;
    }
}

$configStore = ['panchang' => require $baseDir . '/config/panchang.php'];
if (class_exists(Container::class) && class_exists(Repository::class)) {
    $container = new Container;
    $container->instance('config', new Repository($configStore));
    Container::setInstance($container);
}

if (!function_exists('config')) {
    function config(array|string|null $key = null, mixed $default = null): mixed
    {
        global $configStore;
        if ($key === null) { return $configStore; }
        if (is_array($key)) {
            foreach ($key as $path => $value) {
                $segments = explode('.', (string) $path);
                $ref = &$configStore;
                foreach ($segments as $segment) {
                    if (!is_array($ref)) { $ref = []; }
                    if (!array_key_exists($segment, $ref) || !is_array($ref[$segment])) {
                        $ref[$segment] = $ref[$segment] ?? [];
                    }
                    $ref = &$ref[$segment];
                }
                $ref = $value;
                unset($ref);
            }
            return true;
        }
        $segments = explode('.', $key);
        $value = $configStore;
        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) { return $default; }
            $value = $value[$segment];
        }
        return $value;
    }
}

// CLI argument: year
$festivalYear = isset($argv[1]) ? (int) $argv[1] : (int) date('Y');
$timezone = 'Asia/Kolkata';
$latitude = 23.2472446;
$longitude = 69.668339;
$elevation = 0.0;

$sweph = new SwissEphFFI;
$ruleEngine = new FestivalRuleEngine;
$orchestrator = new FestivalFamilyOrchestrator;
$festivalService = new FestivalService($ruleEngine);

$panchangService = new PanchangService(
    $sweph,
    new SunService($sweph),
    new AstronomyService($sweph),
    new PanchangaEngine,
    new MuhurtaService,
    $festivalService,
    new BhadraEngine,
);

$festivalsByDate = [];
$festivalFlat = [];
$festivalStart = CarbonImmutable::create($festivalYear, 1, 1, 0, 0, 0, $timezone);
$festivalEnd = CarbonImmutable::create($festivalYear, 12, 31, 0, 0, 0, $timezone);

echo "Building festivals for {$festivalYear}..." . PHP_EOL;

for ($date = $festivalStart; $date->lessThanOrEqualTo($festivalEnd); $date = $date->addDay()) {
    $todaySnapshot = $panchangService->getFestivalSnapshot($date, $latitude, $longitude, $timezone, $elevation);
    $tomorrowSnapshot = $panchangService->getFestivalSnapshot($date->addDay(), $latitude, $longitude, $timezone, $elevation);
    $festivals = $festivalService->resolveFestivalsForDate($date, $todaySnapshot, $tomorrowSnapshot);

    if ($festivals === []) {
        continue;
    }

    $dateKey = $date->toDateString();
    $festivalsByDate[$dateKey] = $festivals;

    foreach ($festivals as $festival) {
        $festivalFlat[] = [
            'date' => $dateKey,
            'festival' => $festival,
        ];
    }
}

$output = [
    'meta' => [
        'generated_at' => date('c'),
        'type' => 'festivals',
        'year' => $festivalYear,
        'location' => [
            'city' => 'Bhuj',
            'country' => 'IN',
            'latitude' => $latitude,
            'longitude' => $longitude,
            'timezone' => $timezone,
            'elevation' => $elevation,
        ],
    ],
    'festivals' => [
        'title' => "Festivals {$festivalYear} - All festivals for the entire year",
        'year' => $festivalYear,
        'festival_day_count' => count($festivalsByDate),
        'festival_entry_count' => count($festivalFlat),
        'by_date' => $festivalsByDate,
        'flat' => $festivalFlat,
    ],
];

$json = json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($json === false) {
    fwrite(STDERR, 'JSON encoding failed: ' . json_last_error_msg() . PHP_EOL);
    exit(1);
}

$filename = "festivals_{$festivalYear}.json";
file_put_contents($filename, $json . PHP_EOL);

echo "Written {$filename} — " . count($festivalsByDate) . ' festival days, ' . count($festivalFlat) . ' entries.' . PHP_EOL;
