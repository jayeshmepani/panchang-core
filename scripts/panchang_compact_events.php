#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Generate compact festival, vrat, and eclipse date listings.
 *
 * Usage:
 *   php scripts/panchang_compact_events.php --from=02-2025 --to=05-2027 --amanta --en
 *   php scripts/panchang_compact_events.php --from=02-2025 --to=05-2027 --purnimanta --gu --output-dir=scripts/output/compact
 *
 * Output item shape:
 *   {"name": "...", "aliases": ["..."], "dates": ["YYYY-MM-DD"]}
 */

use JayeshMepani\PanchangCore\Traits\CliBootstrap;

$args = $_SERVER['argv'] ?? [];

$parseOptions = static function (array $args): array {
    $options = [
        'from' => null,
        'to' => null,
        'calendar_type' => 'amanta',
        'locale' => 'en',
        'output_dir' => null,
    ];

    foreach (array_slice($args, 1) as $arg) {
        if (str_starts_with($arg, '--from=')) {
            $options['from'] = substr($arg, 7);
            continue;
        }

        if (str_starts_with($arg, '--to=')) {
            $options['to'] = substr($arg, 5);
            continue;
        }

        if (str_starts_with($arg, '--output-dir=')) {
            $options['output_dir'] = substr($arg, 13);
            continue;
        }

        match ($arg) {
            '--amanta' => $options['calendar_type'] = 'amanta',
            '--purnimanta' => $options['calendar_type'] = 'purnimanta',
            '--en' => $options['locale'] = 'en',
            '--hi' => $options['locale'] = 'hi',
            '--gu' => $options['locale'] = 'gu',
            '-h', '--help' => $options['help'] = true,
            default => throw new InvalidArgumentException("Unknown option: {$arg}"),
        };
    }

    return $options;
};

$usage = static function (): string {
    return <<<TEXT
Usage:
  php scripts/panchang_compact_events.php --from=02-2025 --to=05-2027 --amanta --en

Options:
  --from=MM-YYYY      Inclusive start month
  --to=MM-YYYY        Inclusive end month
  --amanta            Use Amanta calendar rules (default)
  --purnimanta        Use Purnimanta calendar rules
  --en|--hi|--gu      Output locale (default: --en)
  --output-dir=PATH   Directory for the three JSON files
                     Default: scripts/output/{calendar_type}/{locale}

TEXT;
};

$parseMonth = static function (?string $value, string $option): DateTimeImmutable {
    if ($value === null || $value === '') {
        throw new InvalidArgumentException("Missing required {$option}=MM-YYYY option.");
    }

    if (! preg_match('/^(0[1-9]|1[0-2])-([0-9]{4})$/', $value, $matches)) {
        throw new InvalidArgumentException("Invalid {$option} value: {$value}. Expected MM-YYYY, e.g. 02-2025.");
    }

    return new DateTimeImmutable(sprintf('%04d-%02d-01', (int) $matches[2], (int) $matches[1]));
};

try {
    $options = $parseOptions($args);
    if (($options['help'] ?? false) === true) {
        echo $usage();
        exit(0);
    }

    $fromMonth = $parseMonth($options['from'], '--from');
    $toMonth = $parseMonth($options['to'], '--to');
    $rangeStart = $fromMonth;
    $rangeEnd = $toMonth->modify('last day of this month');

    if ($rangeStart > $rangeEnd) {
        throw new InvalidArgumentException('--from must be before or equal to --to.');
    }
} catch (InvalidArgumentException $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL . $usage());
    exit(1);
}

$baseDir = is_file(__DIR__ . '/../vendor/autoload.php') ? dirname(__DIR__) : __DIR__;
require $baseDir . '/vendor/autoload.php';
require __DIR__ . '/output_helpers.php';

$_ENV['PANCHANG_CALENDAR_TYPE'] = $options['calendar_type'];
$_SERVER['PANCHANG_CALENDAR_TYPE'] = $options['calendar_type'];
putenv('PANCHANG_CALENDAR_TYPE=' . $options['calendar_type']);

$_ENV['PANCHANG_LOCALE'] = $options['locale'];
$_SERVER['PANCHANG_LOCALE'] = $options['locale'];
putenv('PANCHANG_LOCALE=' . $options['locale']);

CliBootstrap::init($baseDir);

$timezone = 'Asia/Kolkata';
$latitude = 23.2472446;
$longitude = 69.668339;
$elevation = 0.0;

$panchangService = CliBootstrap::makePanchangService();
$outputGen = CliBootstrap::makeOutputGenerator($panchangService);
$eclipseService = CliBootstrap::makeEclipseService();

$dateIsInRange = static function (string $date) use ($rangeStart, $rangeEnd): bool {
    if (! preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $date)) {
        return false;
    }

    $parsed = new DateTimeImmutable($date);

    return $parsed >= $rangeStart && $parsed <= $rangeEnd;
};

/**
 * @param array<string, array<int, array<string, mixed>>> $byDate
 *
 * @return array<int, array{name: string, aliases: array<int, string>, dates: array<int, string>}>
 */
$compactObservances = static function (array $byDate) use ($dateIsInRange): array {
    $grouped = [];

    foreach ($byDate as $date => $entries) {
        if (! $dateIsInRange((string) $date)) {
            continue;
        }

        foreach ($entries as $entry) {
            $name = trim((string) ($entry['name'] ?? $entry['name_key'] ?? ''));
            if ($name === '') {
                continue;
            }

            $key = trim((string) ($entry['name_key'] ?? $name));
            $grouped[$key] ??= [
                'name' => $name,
                'aliases' => [],
                'dates' => [],
            ];

            foreach ((array) ($entry['aliases'] ?? []) as $alias) {
                $alias = trim((string) $alias);
                if ($alias !== '') {
                    $grouped[$key]['aliases'][$alias] = true;
                }
            }

            $grouped[$key]['dates'][(string) $date] = true;
        }
    }

    ksort($grouped, SORT_NATURAL | SORT_FLAG_CASE);

    return array_map(
        static fn (array $entry): array => [
            'name' => $entry['name'],
            'aliases' => array_values(array_keys($entry['aliases'])),
            'dates' => array_values(array_keys($entry['dates'])),
        ],
        array_values($grouped),
    );
};

/**
 * @param array<int, array<string, mixed>> $events
 *
 * @return array<int, array{name: string, aliases: array<int, string>, dates: array<int, string>}>
 */
$compactEclipses = static function (array $events) use ($dateIsInRange): array {
    $grouped = [];

    foreach ($events as $event) {
        $date = (string) ($event['date'] ?? '');
        if (! $dateIsInRange($date)) {
            continue;
        }

        $type = trim((string) ($event['type'] ?? 'Eclipse'));
        $eclipseType = trim((string) ($event['eclipse_type'] ?? ''));
        $localType = trim((string) ($event['local_eclipse_type'] ?? ''));
        $name = trim($eclipseType . ' ' . $type . ' Eclipse');
        $aliases = [];

        if ($localType !== '' && $localType !== $eclipseType) {
            $aliases[] = trim($localType . ' local ' . $type . ' Eclipse');
        }

        $key = $name;
        $grouped[$key] ??= [
            'name' => $name,
            'aliases' => [],
            'dates' => [],
        ];

        foreach ($aliases as $alias) {
            $grouped[$key]['aliases'][$alias] = true;
        }

        $grouped[$key]['dates'][$date] = true;
    }

    ksort($grouped, SORT_NATURAL | SORT_FLAG_CASE);

    return array_map(
        static fn (array $entry): array => [
            'name' => $entry['name'],
            'aliases' => array_values(array_keys($entry['aliases'])),
            'dates' => array_values(array_keys($entry['dates'])),
        ],
        array_values($grouped),
    );
};

$startYear = (int) $rangeStart->format('Y');
$endYear = (int) $rangeEnd->format('Y');
$festivalByDate = [];
$vratByDate = [];
$eclipseEvents = [];

for ($year = $startYear; $year <= $endYear; $year++) {
    $festivalCalendar = $outputGen->generateFestivalsOnlySelected(
        year: $year,
        lat: $latitude,
        lon: $longitude,
        tz: $timezone,
        sections: ['by_date'],
        elevation: $elevation,
        calendarType: $options['calendar_type'],
    );

    $vratCalendar = $outputGen->generateVratsByDateCompact(
        year: $year,
        lat: $latitude,
        lon: $longitude,
        tz: $timezone,
        elevation: $elevation,
        calendarType: $options['calendar_type'],
    );

    $festivalByDate += (array) ($festivalCalendar['by_date'] ?? []);
    $vratByDate += (array) ($vratCalendar['vrats']['by_date'] ?? []);

    foreach ($eclipseService->getEclipsesForYear($year, $latitude, $longitude, $timezone) as $event) {
        $eclipseEvents[] = $event;
    }
}

$outputDir = is_string($options['output_dir']) && $options['output_dir'] !== ''
    ? $options['output_dir']
    : panchang_script_output_dir($baseDir, $options['calendar_type'], $options['locale']);

if (! is_dir($outputDir)) {
    mkdir($outputDir, 0777, true);
}

$rangeSlug = $rangeStart->format('Y_m') . '_to_' . $rangeEnd->format('Y_m');
$files = [
    'festivals' => $outputDir . DIRECTORY_SEPARATOR . "compact_festivals_{$rangeSlug}.json",
    'vrats' => $outputDir . DIRECTORY_SEPARATOR . "compact_vrats_{$rangeSlug}.json",
    'eclipses' => $outputDir . DIRECTORY_SEPARATOR . "compact_eclipses_{$rangeSlug}.json",
];

$meta = [
    'calendar_type' => $options['calendar_type'],
    'from' => $options['from'],
    'to' => $options['to'],
    'location' => [
        'city' => 'Bhuj',
        'country' => 'IN',
        'latitude' => $latitude,
        'longitude' => $longitude,
        'timezone' => $timezone,
        'elevation' => $elevation,
    ],
];

panchang_script_write_json($files['festivals'], [
    'meta' => $meta,
    'festivals' => $compactObservances($festivalByDate),
]);
panchang_script_write_json($files['vrats'], [
    'meta' => $meta,
    'vrats' => $compactObservances($vratByDate),
]);
panchang_script_write_json($files['eclipses'], [
    'meta' => $meta,
    'eclipses' => $compactEclipses($eclipseEvents),
]);

echo 'Written:' . PHP_EOL;
foreach ($files as $file) {
    echo $file . PHP_EOL;
}
