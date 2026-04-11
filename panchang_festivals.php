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
use JayeshMepani\PanchangCore\Core\Localization;
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

/**
 * For adjacent duplicate observances of the same festival name, keep the
 * strongest rule-engine decision (higher winning_score).
 *
 * Tie-break order:
 * 1. higher winning_score
 * 2. winning_reason target_at_karmakala over target_during_observance
 * 3. later date
 */
function consolidateAdjacentByWinningScore(array $festivalFlat): array
{
    $grouped = [];
    foreach ($festivalFlat as $idx => $entry) {
        $name = (string) ($entry['festival']['name'] ?? '');
        if ($name === '') {
            continue;
        }
        $grouped[$name][] = ['idx' => $idx, 'entry' => $entry];
    }

    $remove = [];

    foreach ($grouped as $items) {
        usort($items, static fn (array $a, array $b): int => strcmp((string) $a['entry']['date'], (string) $b['entry']['date']));

        $clusters = [];
        $current = [];
        $previousDate = null;

        foreach ($items as $item) {
            $date = CarbonImmutable::parse((string) $item['entry']['date'], 'Asia/Kolkata');
            if ($previousDate === null || $previousDate->diffInDays($date) <= 1) {
                $current[] = $item;
            } else {
                $clusters[] = $current;
                $current = [$item];
            }
            $previousDate = $date;
        }
        if ($current !== []) {
            $clusters[] = $current;
        }

        foreach ($clusters as $cluster) {
            if (count($cluster) <= 1) {
                continue;
            }

            $best = null;
            foreach ($cluster as $candidate) {
                $festival = (array) ($candidate['entry']['festival'] ?? []);
                $rules = (array) ($festival['rules_applied'] ?? []);
                $score = (int) ($rules['winning_score'] ?? -1);
                $reason = (string) ($rules['winning_reason'] ?? '');
                $date = (string) ($candidate['entry']['date'] ?? '');

                if ($score < 0) {
                    continue;
                }

                if ($best === null) {
                    $best = ['idx' => $candidate['idx'], 'score' => $score, 'reason' => $reason, 'date' => $date];
                    continue;
                }

                $reasonRank = static fn (string $r): int => match ($r) {
                    'target_at_karmakala' => 2,
                    'target_during_observance' => 1,
                    default => 0,
                };

                if (
                    $score > $best['score']
                    || ($score === $best['score'] && $reasonRank($reason) > $reasonRank((string) $best['reason']))
                    || ($score === $best['score'] && $reasonRank($reason) === $reasonRank((string) $best['reason']) && strcmp($date, (string) $best['date']) > 0)
                ) {
                    $best = ['idx' => $candidate['idx'], 'score' => $score, 'reason' => $reason, 'date' => $date];
                }
            }

            if ($best === null) {
                continue;
            }

            foreach ($cluster as $candidate) {
                if ($candidate['idx'] !== $best['idx']) {
                    $remove[$candidate['idx']] = true;
                }
            }
        }
    }

    if ($remove === []) {
        return $festivalFlat;
    }

    $filtered = [];
    foreach ($festivalFlat as $idx => $entry) {
        if (!isset($remove[$idx])) {
            $filtered[] = $entry;
        }
    }

    return $filtered;
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
    $yesterdaySnapshot = $panchangService->getFestivalSnapshot($date->subDay(), $latitude, $longitude, $timezone, $elevation, null, $calendarType);
    $todaySnapshot = $panchangService->getFestivalSnapshot($date, $latitude, $longitude, $timezone, $elevation, null, $calendarType);
    $tomorrowSnapshot = $panchangService->getFestivalSnapshot($date->addDay(), $latitude, $longitude, $timezone, $elevation, null, $calendarType);
    $festivals = $festivalService->resolveFestivalsForDate($date, $todaySnapshot, $tomorrowSnapshot, $yesterdaySnapshot);

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

// Resolve day_after festivals (e.g. Holi after Holika Dahan)
// These can't be resolved in FestivalService::resolveFestivalsForDate because
// that method only has access to today's and tomorrow's snapshots.
foreach (FestivalService::FESTIVALS as $festName => $rules) {
    if ((string) ($rules['type'] ?? '') !== 'day_after') {
        continue;
    }
    $parentName = (string) ($rules['parent_festival'] ?? '');
    $daysAfter = (int) ($rules['days_after'] ?? 1);

    if ($parentName === '') {
        continue;
    }

    // Find all dates where the parent festival was observed
    foreach ($festivalsByDate as $obsDate => $obsFestivals) {
        foreach ($obsFestivals as $obsFestival) {
            // Check if this is the parent festival (compare English names)
            // The observed name is translated, so we need to match against all known translations
            $parentEnglishName = Localization::translate('Festival', $parentName);
            if ($obsFestival['name'] === $parentEnglishName) {
                // Found parent festival on $obsDate
                $holiDate = CarbonImmutable::parse($obsDate, $timezone)->addDays($daysAfter);
                $holiDateKey = $holiDate->toDateString();

                // Only add if within the festival year
                if ($holiDate->year === $festivalYear) {
                    $regions = $rules['regions'] ?? ['Pan-India'];
                    $holiFestival = [
                        'name' => Localization::translate('Festival', $festName),
                        'description' => $rules['description'],
                        'deity' => Localization::translate('Deity', $rules['deity'] ?? ''),
                        'fasting' => $rules['fasting'] ?? false,
                        'regions' => array_map(fn ($r) => Localization::translate('Region', $r), $regions),
                        'observance_note' => 'Observed ' . $daysAfter . ' day(s) after ' . $parentEnglishName,
                    ];

                    if (!isset($festivalsByDate[$holiDateKey])) {
                        $festivalsByDate[$holiDateKey] = [];
                    }
                    $festivalsByDate[$holiDateKey][] = $holiFestival;
                    $festivalFlat[] = [
                        'date' => $holiDateKey,
                        'festival' => $holiFestival,
                    ];
                }
            }
        }
    }
}

// Consolidate adjacent duplicates where rule-engine winning_score clearly
// indicates one stronger observance day.
$festivalFlat = consolidateAdjacentByWinningScore($festivalFlat);
$festivalsByDate = [];
foreach ($festivalFlat as $entry) {
    $dateKey = (string) $entry['date'];
    $festivalsByDate[$dateKey] ??= [];
    $festivalsByDate[$dateKey][] = $entry['festival'];
}
krsort($festivalsByDate);
$festivalsByDate = array_reverse($festivalsByDate, true);

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
