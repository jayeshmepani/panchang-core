#!/usr/bin/env php
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Carbon\CarbonImmutable;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use JayeshMepani\PanchangCore\Traits\CliBootstrap;

$configStore = [
    'panchang' => [
        'ephe_path' => '',
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

CliBootstrap::init(dirname(__DIR__));
$panchangService = CliBootstrap::makePanchangService();

$date = CarbonImmutable::create(2026, 1, 19, 0, 0, 0, $timezone);
$details = $panchangService->getDayDetails($date, $latitude, $longitude, $timezone, $elevation);

echo 'Date: ' . $date->toDateString() . PHP_EOL;
echo 'Hindu Calendar: ' . json_encode($details['Hindu_Calendar'], JSON_PRETTY_PRINT) . PHP_EOL;
echo 'Tithi: ' . json_encode($details['Tithi'], JSON_PRETTY_PRINT) . PHP_EOL;
echo 'Festivals: ' . json_encode($details['Festivals'], JSON_PRETTY_PRINT) . PHP_EOL;
