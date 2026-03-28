#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Carbon\CarbonImmutable;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use JayeshMepani\PanchangCore\Astronomy\AstronomyService;
use JayeshMepani\PanchangCore\Astronomy\EclipseService;
use JayeshMepani\PanchangCore\Astronomy\SunService;
use JayeshMepani\PanchangCore\Festivals\FestivalFamilyOrchestrator;
use JayeshMepani\PanchangCore\Festivals\FestivalRuleEngine;
use JayeshMepani\PanchangCore\Festivals\FestivalService;
use JayeshMepani\PanchangCore\Festivals\Utils\BhadraEngine;
use JayeshMepani\PanchangCore\Panchanga\ElectionalEvaluator;
use JayeshMepani\PanchangCore\Panchanga\MuhurtaService;
use JayeshMepani\PanchangCore\Panchanga\PanchangaEngine;
use JayeshMepani\PanchangCore\Panchanga\PanchangService;
use SwissEph\FFI\SwissEphFFI;

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false || $value === null) {
            return $default;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            $lower = strtolower($trimmed);

            if ($lower === 'true' || $lower === '(true)') {
                return true;
            }
            if ($lower === 'false' || $lower === '(false)') {
                return false;
            }
            if ($lower === 'null' || $lower === '(null)') {
                return null;
            }
            if ($lower === 'empty' || $lower === '(empty)') {
                return '';
            }

            return $trimmed;
        }

        return $value;
    }
}

$configStore = [
    'panchang' => require __DIR__ . '/config/panchang.php',
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

        if ($key === null) {
            return $configStore;
        }

        if (is_array($key)) {
            foreach ($key as $path => $value) {
                $segments = explode('.', (string) $path);
                $ref = &$configStore;
                foreach ($segments as $segment) {
                    if (!is_array($ref)) {
                        $ref = [];
                    }
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

        $segments = explode('.', (string) $key);
        $value = $configStore;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
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

$eclipseService = new EclipseService($sweph);

$festivalsByDate = [];
$festivalFlat = [];
$festivalYear = 2026;
$festivalStart = CarbonImmutable::create($festivalYear, 1, 1, 0, 0, 0, $timezone);
$festivalEnd = CarbonImmutable::create($festivalYear, 12, 31, 0, 0, 0, $timezone);

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

$eclipsesByYear = [];
$eclipsesFlat = [];

for ($year = 2026; $year <= 2032; $year++) {
    $events = $eclipseService->getEclipsesForYear($year, $latitude, $longitude, $timezone);
    $eclipsesByYear[(string) $year] = $events;

    foreach ($events as $event) {
        $eclipsesFlat[] = $event;
    }
}

$now = CarbonImmutable::now($timezone);
$todayDate = $now->startOfDay();
$todayDetails = $panchangService->getDayDetails(
    date: $todayDate,
    lat: $latitude,
    lon: $longitude,
    tz: $timezone,
    elevation: $elevation,
    ayanamsaAt: $now,
);

// Get complete Muhurta evaluation using ElectionalEvaluator
// Extract today's panchanga elements for Muhurta evaluation
$tithiNumber = $todayDetails['tithi']['number'] ?? 1;
$varaNumber = $todayDetails['vara']['number'] ?? 0;
$nakshatraNumber = $todayDetails['Nakshatra']['number'] ?? 1;
$lagnaNumber = $todayDetails['lagna']['number'] ?? 1;
$moonSignIdx = $todayDetails['moon_sign']['index'] ?? 0;
$isKrishnaPaksha = $todayDetails['paksha']['is_krishna'] ?? false;

// Get sunrise/sunset times from PanchangService
$sunriseStr = $todayDetails['Sunrise'];
$sunsetStr = $todayDetails['Sunset'];
$nextSunriseStr = $todayDetails['next_sunrise_iso'] ?? $todayDetails['Sunrise'];

// Convert time strings to decimal hours using ElectionalEvaluator helper
$sunrise = ElectionalEvaluator::timeStringToDecimal($sunriseStr);
$sunset = ElectionalEvaluator::timeStringToDecimal($sunsetStr);
$nextSunrise = ElectionalEvaluator::timeStringToDecimal($nextSunriseStr);
$currentTime = $now->format('G') + ($now->format('i') / 60.0) + ($now->format('s') / 3600.0);

// Get nakshatra timing using ElectionalEvaluator helper
$nakshatraData = $todayDetails['Varjyam'] ?? null;
[$nakshatraNumberActual, $nakshatraStart, $nakshatraDuration] = ElectionalEvaluator::getNakshatraTiming($nakshatraData, $sunrise, 60.0);

$muhurtaEvaluation = [
    'panchaka_dosha' => ElectionalEvaluator::calculatePanchakaDosha($tithiNumber, $varaNumber + 1, $nakshatraNumber, $lagnaNumber),
    'dagdha_tithi' => ElectionalEvaluator::calculateDagdhaTithi($tithiNumber, $moonSignIdx),
    'dagdha_yoga' => ElectionalEvaluator::calculateDagdhaYoga($varaNumber, $tithiNumber),
    'nakshatra_vedha' => ElectionalEvaluator::calculateNakshatraVedha(0, $nakshatraNumber - 1),
    'bhadra' => ElectionalEvaluator::calculateBhadra($moonSignIdx),
    'rikta_tithi' => ElectionalEvaluator::calculateRiktaTithi($tithiNumber, $isKrishnaPaksha),
    'varjyam' => ElectionalEvaluator::calculateVarjyam($nakshatraNumberActual, $nakshatraStart, $nakshatraDuration),
    'amrita_kaal' => ElectionalEvaluator::calculateAmritaKaal($varaNumber, $sunrise, $sunset, $nextSunrise, $currentTime),
    'abhijit_cancellation' => ElectionalEvaluator::calculateAbhijitCancellation($sunrise, $sunset, $varaNumber, $currentTime),
];

// Get Karmakala requirements for key activities
$karmakalaRequirements = [
    'vivaha' => ElectionalEvaluator::getKarmakalaRequirements('vivaha'),
    'griha_pravasha' => ElectionalEvaluator::getKarmakalaRequirements('griha_pravasha'),
    'upanayana' => ElectionalEvaluator::getKarmakalaRequirements('upanayana'),
    'namakarana' => ElectionalEvaluator::getKarmakalaRequirements('namakarana'),
];

// Generate rejection report for today's Muhurta
$rejectionReport = ElectionalEvaluator::generateRejectionReport($muhurtaEvaluation, 'general_auspicious');

// Get complete activity matrix
$completeActivityMatrix = ElectionalEvaluator::getCompleteActivityMatrix();

$output = [
    'meta' => [
        'generated_at' => $now->toIso8601String(),
        'location' => [
            'city' => $city,
            'country' => $country,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'timezone' => $timezone,
            'elevation' => $elevation,
        ],
        'config_source' => __DIR__ . '/config/panchang.php',
    ],
    'festivals_2026' => [
        'title' => 'Festivals 2026 - All festivals for the entire year',
        'year' => $festivalYear,
        'festival_day_count' => count($festivalsByDate),
        'festival_entry_count' => count($festivalFlat),
        'by_date' => $festivalsByDate,
        'flat' => $festivalFlat,
    ],
    'eclipses_2026_2032' => [
        'title' => 'Eclipses 2026-2032 - All eclipses for 7 years',
        'from_year' => 2026,
        'to_year' => 2032,
        'total_eclipse_count' => count($eclipsesFlat),
        'by_year' => $eclipsesByYear,
        'flat' => $eclipsesFlat,
    ],
    'todays_complete_details' => [
        'title' => "Today's Complete Details - Every single data point from the package",
        'input_now' => $now->toIso8601String(),
        'date' => $todayDate->toDateString(),
        'details' => $todayDetails,
    ],
    'muhurta_evaluation' => [
        'title' => 'Complete Muhurta Evaluation - All newly implemented classical Muhurta engines',
        'input_parameters' => [
            'tithi_number' => $tithiNumber,
            'vara_number' => $varaNumber,
            'nakshatra_number' => $nakshatraNumber,
            'lagna_number' => $lagnaNumber,
            'moon_sign_idx' => $moonSignIdx,
            'is_krishna_paksha' => $isKrishnaPaksha,
            'sunrise' => $sunrise,
            'sunset' => $sunset,
            'next_sunrise' => $nextSunrise,
            'current_time' => $currentTime,
        ],
        'evaluation_results' => $muhurtaEvaluation,
        'rejection_report' => $rejectionReport,
    ],
    'karmakala_requirements' => [
        'title' => 'Karmakala Event Window Requirements - Classical rules for key activities',
        'requirements' => $karmakalaRequirements,
    ],
    'complete_activity_matrix' => [
        'title' => 'Complete Classical Activity Matrix - All 16+ Muhurta activities with exact classical requirements',
        'activity_count' => count($completeActivityMatrix),
        'activities' => $completeActivityMatrix,
    ],
];

$json = json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if ($json === false) {
    fwrite(STDERR, 'JSON encoding failed: ' . json_last_error_msg() . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, $json . PHP_EOL);
