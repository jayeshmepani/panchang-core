#!/usr/bin/env php
<?php

declare(strict_types=1);

$baseDir = is_file(__DIR__ . '/vendor/autoload.php') ? __DIR__ : dirname(__DIR__);
require $baseDir . '/vendor/autoload.php';

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use JayeshMepani\PanchangCore\Astronomy\AstronomyService;
use JayeshMepani\PanchangCore\Astronomy\SunService;
use JayeshMepani\PanchangCore\Festivals\FestivalRuleEngine;
use JayeshMepani\PanchangCore\Festivals\FestivalService;
use JayeshMepani\PanchangCore\Festivals\Utils\BhadraEngine;
use JayeshMepani\PanchangCore\Panchanga\MuhurtaService;
use JayeshMepani\PanchangCore\Panchanga\PanchangaEngine;
use JayeshMepani\PanchangCore\Panchanga\PanchangService;
use SwissEph\FFI\SwissEphFFI;

// Simple env helper if not defined
if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        return $value === false ? $default : $value;
    }
}

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

// Default parameters
$timezone = 'Asia/Kolkata';
$latitude = 23.2472446;
$longitude = 69.668339;
$elevation = 0.0;

// Allow overriding via CLI arguments: php panchang_month_output.php [year] [month]
$year = isset($argv[1]) ? (int) $argv[1] : (int) date('Y');
$month = isset($argv[2]) ? (int) $argv[2] : (int) date('m');

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

$calendar = $panchangService->getMonthCalendar(
    year: $year,
    month: $month,
    lat: $latitude,
    lon: $longitude,
    tz: $timezone,
    elevation: $elevation
);

$output = [
    'meta' => [
        'generated_at' => date('c'),
        'year' => $year,
        'month' => $month,
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
