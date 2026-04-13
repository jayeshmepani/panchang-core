#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Generate today's complete panchang JSON.
 *
 * Usage: php panchang_today.php
 * Output: today_panchang.json
 *
 * This data changes daily — run whenever you need current data.
 */
$baseDir = is_file(__DIR__ . '/vendor/autoload.php') ? __DIR__ : dirname(__DIR__);
require $baseDir . '/vendor/autoload.php';

use Carbon\CarbonImmutable;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use JayeshMepani\PanchangCore\Astronomy\AstronomyService;
use JayeshMepani\PanchangCore\Astronomy\EclipseService;
use JayeshMepani\PanchangCore\Astronomy\SunService;
use JayeshMepani\PanchangCore\Core\Localization;
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

// Get calendar type from config
$calendarType = config('panchang.defaults.calendar_type', 'purnimanta');

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

$timezone = 'Asia/Kolkata';
$latitude = 23.2472446;
$longitude = 69.668339;
$elevation = 0.0;
$city = 'Bhuj';
$country = 'IN';

$sweph = new SwissEphFFI;
$ruleEngine = new FestivalRuleEngine;
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

$eclipseService = new EclipseService($sweph);

$now = CarbonImmutable::now($timezone);
$todayDate = $now->startOfDay();

// First get sunrise time to use as fixed calculation reference
$tempDetails = $panchangService->getDayDetails(
    date: $todayDate,
    lat: $latitude,
    lon: $longitude,
    tz: $timezone,
    elevation: $elevation,
);
$sunriseTime = CarbonImmutable::createFromFormat('d/m/Y h:i:s A', $tempDetails['sunrise_dt'], $timezone)
    ?: CarbonImmutable::parse($tempDetails['sunrise_dt'], $timezone);

$todayDetails = $panchangService->getDayDetails(
    date: $todayDate,
    lat: $latitude,
    lon: $longitude,
    tz: $timezone,
    elevation: $elevation,
    calculationAt: $sunriseTime,
    calendarType: $calendarType
);

$dailyMuhurtaEvaluation = $panchangService->getDailyMuhurtaEvaluation(
    date: $todayDate,
    lat: $latitude,
    lon: $longitude,
    tz: $timezone,
    currentAt: $sunriseTime,
    elevation: $elevation,
);

// Output = allinone minus festivals minus eclipses (no more, no less)
$output = [
    'meta' => [
        'generated_at' => $now->toIso8601String(),
        'muhurta_mode' => 'transit_only',
        'location' => [
            'city' => $city,
            'country' => $country,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'timezone' => $timezone,
            'elevation' => $elevation,
        ],
        'config_source' => $baseDir . '/config/panchang.php',
    ],
    'todays_complete_details' => [
        'title' => Localization::translate('String', "Today's Complete Details - Every single data point from the package"),
        'input_now' => $now->toIso8601String(),
        'date' => $todayDate->toDateString(),
        'details' => $todayDetails,
    ],
    'muhurta_evaluation' => array_merge(
        [
            'scope' => 'transit_only',
            'notes' => [
                'No natal or person-specific inputs are used.',
                'Evaluation is derived only from current Panchang and transit state for the configured location/time.',
            ],
        ],
        $dailyMuhurtaEvaluation
    ),
];

$json = json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($json === false) {
    fwrite(STDERR, 'JSON encoding failed: ' . json_last_error_msg() . PHP_EOL);
    exit(1);
}

file_put_contents('today_panchang.json', $json . PHP_EOL);
echo $json . PHP_EOL;
