#!/usr/bin/env php
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

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

$configStore = [
    'panchang' => [
        'ephe_path' => '',
        'ayanamsa' => 'LAHIRI',
        'defaults' => [
            'measurement_system' => 'indian_metric',
            'date_time_format' => 'indian_12h',
            'time_notation' => '12h',
            'coordinate_format' => 'decimal',
            'angle_unit' => 'degree',
            'duration_format' => 'mixed',
        ],
    ],
];

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
        $segments = explode('.', (string) $key);
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

$sweph = new SwissEphFFI;
$ruleEngine = new FestivalRuleEngine;
$orchestrator = new FestivalFamilyOrchestrator;
$festivalService = new FestivalService($ruleEngine, $orchestrator);

$panchangService = new PanchangService(
    $sweph,
    new SunService($sweph),
    new AstronomyService($sweph),
    new PanchangaEngine,
    new MuhurtaService,
    $festivalService,
    new BhadraEngine,
);

$date = CarbonImmutable::create(2026, 1, 19, 0, 0, 0, $timezone);
$details = $panchangService->getDayDetails($date, $latitude, $longitude, $timezone, $elevation);

echo 'Date: ' . $date->toDateString() . PHP_EOL;
echo 'Hindu Calendar: ' . json_encode($details['Hindu_Calendar'], JSON_PRETTY_PRINT) . PHP_EOL;
echo 'Tithi: ' . json_encode($details['Tithi'], JSON_PRETTY_PRINT) . PHP_EOL;
echo 'Festivals: ' . json_encode($details['Festivals'], JSON_PRETTY_PRINT) . PHP_EOL;
