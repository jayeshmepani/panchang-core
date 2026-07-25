#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Chandra Darśana Hybrid Resolver Engine
 *
 * Combines:
 *  1) Modern Astronomical Layer (100% Untouched Yallop TN69 q-criterion).
 *  2) SS 10.1: Waxing ecliptic separation ≥ 12.0° at local sunset.
 *  3) Dharma Sindhu: 3-muhūrta Aparāhṇa Dvitīyā rule.
 *  4) Dharma Sindhu: 6-muhūrta Pradoṣa Dvitīyā rule.
 *  5) Nirṇayāmṛta: Kṣaya Pratipadā Day-2 deferral mandate.
 */

$baseDir = is_file(__DIR__ . '/../vendor/autoload.php') ? dirname(__DIR__) : __DIR__;
require $baseDir . '/vendor/autoload.php';
require __DIR__ . '/output_helpers.php';
require __DIR__ . '/lib/chandra_yallop.php';

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Traits\CliBootstrap;
use JmeEph\FFI\JmeEphFFI;

const HYBRID_MAX_POST_AMAVASYA_EVENINGS = 5;
const HYBRID_SS10_1_ECLIPTIC_MIN_DEG = 12.0;
const HYBRID_DHARMA_SINDHU_APARAHNA_MIN_MUHURTAS = 3.0;
const HYBRID_DHARMA_SINDHU_PRADOSHA_MIN_MUHURTAS = 6.0;

$parseOptions = static function (array $argv): array {
    $options = [
        'from' => '2024-01-01',
        'to' => '2028-12-31',
        'lat' => 23.2472446,
        'lon' => 69.668339,
        'tz' => 'Asia/Kolkata',
        'elevation' => 0.0,
        'calendar_type' => 'amanta',
        'min_category' => 'B',
        'apply_danjon_guard' => true,
        'output' => null,
    ];

    foreach (array_slice($argv, 1) as $arg) {
        if (str_starts_with((string) $arg, '--from=')) {
            $options['from'] = substr((string) $arg, 7);
        } elseif (str_starts_with((string) $arg, '--to=')) {
            $options['to'] = substr((string) $arg, 5);
        } elseif (str_starts_with((string) $arg, '--lat=')) {
            $options['lat'] = (float) substr((string) $arg, 6);
        } elseif (str_starts_with((string) $arg, '--lon=')) {
            $options['lon'] = (float) substr((string) $arg, 6);
        } elseif (str_starts_with((string) $arg, '--tz=')) {
            $options['tz'] = substr((string) $arg, 5);
        } elseif (str_starts_with((string) $arg, '--elevation=')) {
            $options['elevation'] = (float) substr((string) $arg, 12);
        } elseif (str_starts_with((string) $arg, '--min-category=')) {
            $options['min_category'] = strtoupper(substr((string) $arg, 15));
        } elseif (str_starts_with((string) $arg, '--output=')) {
            $options['output'] = substr((string) $arg, 9);
        } elseif ($arg === '--danjon-guard') {
            $options['apply_danjon_guard'] = true;
        } elseif ($arg === '--no-danjon-guard') {
            $options['apply_danjon_guard'] = false;
        } elseif ($arg === '--amanta') {
            $options['calendar_type'] = 'amanta';
        } elseif ($arg === '--purnimanta') {
            $options['calendar_type'] = 'purnimanta';
        } elseif ($arg === '-h' || $arg === '--help') {
            $options['help'] = true;
        } else {
            throw new InvalidArgumentException('Unknown option: ' . $arg);
        }
    }

    if (!in_array((string) $options['min_category'], ['A', 'B', 'C', 'D'], true)) {
        throw new InvalidArgumentException('--min-category must be A, B, C, or D');
    }

    return $options;
};

$usage = static fn (): string => <<<TEXT
Usage:
  php scripts/chandra_hybrid_resolver.php --from=2024-01-01 --to=2028-12-31 --amanta

Chandra Darśana Hybrid Engine:
  - 100% Modern Yallop q TN69 Astronomical Model
  - SS 10.1: Ecliptic Separation ≥ 12°
  - Dharma Sindhu: 3-Muhūrta Aparāhṇa Dvitīyā
  - Dharma Sindhu: 6-Muhūrta Pradoṣa Dvitīyā
  - Nirṇayāmṛta: Kṣaya Pratipadā Day-2 Deferral

Options:
  --from=YYYY-MM-DD          Start date (default: 2024-01-01)
  --to=YYYY-MM-DD            End date (default: 2028-12-31)
  --lat=FLOAT                Latitude (default: 23.2472446)
  --lon=FLOAT                Longitude (default: 69.668339)
  --tz=TZ                    Timezone (default: Asia/Kolkata)
  --elevation=FLOAT          Elevation in meters
  --min-category=A|B|C|D     Yallop floor category (default: B)
  --danjon-guard             Enable ARCL ≥ 7° guard (default: ON)
  --no-danjon-guard          Disable Danjon guard
  --output=PATH              Output JSON path

TEXT;

try {
    $options = $parseOptions($_SERVER['argv'] ?? []);
    if ($options['help'] ?? false) {
        echo $usage();
        exit(0);
    }
    $rangeStart = CarbonImmutable::parse((string) $options['from'], (string) $options['tz']);
    $rangeEnd = CarbonImmutable::parse((string) $options['to'], (string) $options['tz']);
    if ($rangeStart->greaterThan($rangeEnd)) {
        throw new InvalidArgumentException('--from must be before or equal to --to.');
    }
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . PHP_EOL . $usage());
    exit(1);
}

$_ENV['PANCHANG_CALENDAR_TYPE'] = (string) $options['calendar_type'];
$_SERVER['PANCHANG_CALENDAR_TYPE'] = (string) $options['calendar_type'];
putenv('PANCHANG_CALENDAR_TYPE=' . (string) $options['calendar_type']);

CliBootstrap::init($baseDir);
$service = CliBootstrap::makePanchangService();

$jme = new JmeEphFFI();
$configureJme = new ReflectionMethod(CliBootstrap::class, 'configureJme');
$configureJme->invoke(null, $jme);

/** @var array<string, array<string, mixed>> $dayBundleCache */
$dayBundleCache = [];

$extractJd = static function (mixed $value): ?float {
    if (is_float($value) || is_int($value)) {
        return (float) $value;
    }
    if (is_array($value) && isset($value['jd'])) {
        return (float) $value['jd'];
    }
    return null;
};

$dayBundle = static function (CarbonImmutable $date) use (&$dayBundleCache, $service, $options, $extractJd): array {
    $key = $date->toDateString();
    if (isset($dayBundleCache[$key])) {
        return $dayBundleCache[$key];
    }

    $details = $service->getFestivalSnapshot(
        $date,
        (float) $options['lat'],
        (float) $options['lon'],
        (string) $options['tz'],
        (float) $options['elevation'],
        null,
        (string) $options['calendar_type'],
        false
    );
    $ctx = (array) ($details['Resolution_Context'] ?? []);

    return $dayBundleCache[$key] = [
        'date' => $key,
        'tithi_index_abs' => (int) ($ctx['tithi_index_abs'] ?? 0),
        'tithi_start_jd' => (float) ($ctx['tithi_start_jd'] ?? 0.0),
        'tithi_end_jd' => (float) ($ctx['tithi_end_jd'] ?? 0.0),
        'sunrise_jd' => (float) ($ctx['sunrise_jd'] ?? 0.0),
        'sunset_jd' => (float) ($ctx['sunset_jd'] ?? 0.0),
        'next_sunrise_jd' => (float) ($ctx['next_sunrise_jd'] ?? 0.0),
        'moonrise_jd' => $extractJd($details['Moonrise_JD'] ?? ($details['Moonrise'] ?? null)),
        'moonset_jd' => $extractJd($details['Moonset_JD'] ?? ($details['Moonset'] ?? null)),
        'snapshot_elong_deg' => fmod((float) ($ctx['moon_sun_elongation_at_sunset_degrees'] ?? 0.0) + 360.0, 360.0),
        'illumination_percent' => (float) ($ctx['moon_illumination_at_sunset_percent'] ?? 0.0),
    ];
};

/**
 * Evaluates the Dharma Sindhu and Nirṇayāmṛta classical rules for a given day.
 */
$evaluateClassicalHybridGates = static function (CarbonImmutable $date, array $day, array $prevDay, array $nextDay) use ($dayBundle): array {
    $sunrise = (float) $day['sunrise_jd'];
    $sunset = (float) $day['sunset_jd'];
    $nextSunrise = (float) $day['next_sunrise_jd'];

    $daylightDuration = $sunset - $sunrise;
    $dayMuhurta = $daylightDuration > 0.0 ? $daylightDuration / 15.0 : 0.0;

    $nightDuration = $nextSunrise - $sunset;
    $nightMuhurta = $nightDuration > 0.0 ? $nightDuration / 15.0 : 0.0;

    // --- 1. Dharma Sindhu: 3-Muhūrta Aparāhṇa Dvitīyā ---
    // Aparāhṇa spans the 10th, 11th, and 12th daylight muhūrtas (3 muhūrtas long).
    $aparahnaStart = $sunrise + (9.0 * $dayMuhurta);
    $aparahnaEnd = $sunrise + (12.0 * $dayMuhurta);

    // Determine Dvitīyā boundaries for the civil day
    $absTithi = (int) $day['tithi_index_abs'];
    $dvitiyaStartJd = 0.0;
    $dvitiyaEndJd = 0.0;

    if ($absTithi === 2) {
        $dvitiyaStartJd = (float) $day['tithi_start_jd'];
        $dvitiyaEndJd = (float) $day['tithi_end_jd'];
    } elseif ($absTithi === 1) {
        $dvitiyaStartJd = (float) $day['tithi_end_jd'];
        $dvitiyaEndJd = (int) $nextDay['tithi_index_abs'] === 2 ? (float) $nextDay['tithi_end_jd'] : $dvitiyaStartJd;
    } elseif ($absTithi === 30) {
        if ((int) $nextDay['tithi_index_abs'] === 2) {
            $dvitiyaStartJd = (float) $nextDay['tithi_start_jd'];
            $dvitiyaEndJd = (float) $nextDay['tithi_end_jd'];
        }
    }

    $aparahnaOverlapJd = max(0.0, min($aparahnaEnd, $dvitiyaEndJd) - max($aparahnaStart, $dvitiyaStartJd));
    $aparahnaMuhurtas = $dayMuhurta > 0.0 ? $aparahnaOverlapJd / $dayMuhurta : 0.0;
    $ds3MuhurtaAparahnaPassed = $aparahnaMuhurtas >= HYBRID_DHARMA_SINDHU_APARAHNA_MIN_MUHURTAS - 1e-6;

    // --- 2. Dharma Sindhu: 6-Muhūrta Pradoṣa Dvitīyā ---
    // Pradoṣa spans the first 6 muhūrtas after sunset.
    $pradoshaStart = $sunset;
    $pradoshaEnd = $sunset + (6.0 * $nightMuhurta);

    $pradoshaOverlapJd = max(0.0, min($pradoshaEnd, $dvitiyaEndJd) - max($pradoshaStart, $dvitiyaStartJd));
    $pradoshaMuhurtas = $nightMuhurta > 0.0 ? $pradoshaOverlapJd / $nightMuhurta : 0.0;
    $ds6MuhurtaPradoshaPassed = $pradoshaMuhurtas >= HYBRID_DHARMA_SINDHU_PRADOSHA_MIN_MUHURTAS - 1e-6;

    // --- 3. Nirṇayāmṛta: Kṣaya Pratipadā Day-2 Deferral ---
    // Shukla Pratipadā (index 1) is kṣaya if it starts after sunrise of Day 1 and ends before sunrise of Day 2.
    $isKsayaPratipada = false;
    if ($absTithi === 30) {
        $amavasyaEnd = (float) $day['tithi_end_jd'];
        $pratipadaEnd = (float) $nextDay['tithi_start_jd']; // or tithi_end_jd of Pratipadā
        if ((int) $nextDay['tithi_index_abs'] === 2 && $amavasyaEnd > $sunrise && $amavasyaEnd < $nextSunrise) {
            // Pratipadā was completely contained between sunrise 1 and sunrise 2 (Kṣaya)
            $isKsayaPratipada = true;
        }
    } elseif ($absTithi === 1) {
        $pratipadaStart = (float) $day['tithi_start_jd'];
        $pratipadaEnd = (float) $day['tithi_end_jd'];
        if ($pratipadaStart > $sunrise && $pratipadaEnd < $nextSunrise) {
            $isKsayaPratipada = true;
        }
    }

    // Deferral mandate per Nirṇayāmṛta: If Pratipadā is Kṣaya on Day 1, observation must defer to Day 2.
    $nirnayamritaDay2DeferralEnforced = $isKsayaPratipada;

    return [
        'dharma_sindhu' => [
            'aparahna_start_jd' => $aparahnaStart,
            'aparahna_end_jd' => $aparahnaEnd,
            'aparahna_dvitiya_muhurtas' => round($aparahnaMuhurtas, 4),
            'ds_3_muhurta_aparahna_passed' => $ds3MuhurtaAparahnaPassed,
            'pradosha_start_jd' => $pradoshaStart,
            'pradosha_end_jd' => $pradoshaEnd,
            'pradosha_dvitiya_muhurtas' => round($pradoshaMuhurtas, 4),
            'ds_6_muhurta_pradosha_passed' => $ds6MuhurtaPradoshaPassed,
        ],
        'nirnayamrita' => [
            'is_ksaya_pratipada' => $isKsayaPratipada,
            'day2_deferral_enforced' => $nirnayamritaDay2DeferralEnforced,
        ],
    ];
};

$discoverConjunctionSeasons = static function () use ($rangeStart, $rangeEnd, $dayBundle): array {
    $seasons = [];
    for ($d = $rangeStart->subDays(3); $d->lessThanOrEqualTo($rangeEnd->addDays(7)); $d = $d->addDay()) {
        $day = $dayBundle($d);
        $abs = (int) $day['tithi_index_abs'];

        if ($abs === 30 && (float) $day['tithi_end_jd'] > 0.0) {
            $seasons[sprintf('%.5F', (float) $day['tithi_end_jd'])] = [
                'amavasya_end_jd' => (float) $day['tithi_end_jd'],
                'anchor_date' => $d->toDateString(),
            ];
        }
        if ($abs === 1 && (float) $day['tithi_start_jd'] > 0.0) {
            $anchor = (float) $day['tithi_start_jd'] < (float) $day['sunrise_jd']
                ? $d->subDay()->toDateString()
                : $d->toDateString();
            $seasons[sprintf('%.5F', (float) $day['tithi_start_jd'])] = [
                'amavasya_end_jd' => (float) $day['tithi_start_jd'],
                'anchor_date' => $anchor,
            ];
        }
    }
    ksort($seasons);
    return array_values($seasons);
};

$evaluateEveningHybrid = static function (
    array $day,
    array $prevDay,
    array $nextDay,
    float $amavasyaEndJd,
    CarbonImmutable $date
) use ($jme, $options, $evaluateClassicalHybridGates): array {
    $sunset = (float) $day['sunset_jd'];
    $moonset = $day['moonset_jd'];
    $lat = (float) $options['lat'];
    $lon = (float) $options['lon'];
    $elev = (float) $options['elevation'];
    $minCat = (string) $options['min_category'];
    $applyDanjonGuard = (bool) $options['apply_danjon_guard'];

    if ($sunset <= $amavasyaEndJd) {
        return [
            'allowed' => false,
            'status_code' => 'REJECTED_BEFORE_CONJUNCTION',
            'reason' => 'Sunset occurred before Amāvasyā end.',
            'metrics' => null,
        ];
    }

    if ($moonset === null || !is_finite((float) $moonset)) {
        return [
            'allowed' => false,
            'status_code' => 'REJECTED_MOONSET_UNAVAILABLE',
            'reason' => 'Moonset JD unavailable.',
            'metrics' => null,
        ];
    }

    $moonsetF = (float) $moonset;
    $lagDays = $moonsetF - $sunset;
    if ($lagDays <= 0.0) {
        return [
            'allowed' => false,
            'status_code' => 'REJECTED_NO_POSITIVE_LAG',
            'reason' => 'Moon sets before or simultaneously with sunset.',
            'metrics' => null,
        ];
    }

    // --- 100% Modern Layer: Full Yallop TN69 Astronomical Evaluation ---
    $y = chandra_yallop_evaluate_evening($jme, $sunset, $moonsetF, $lat, $lon, $elev);
    if (!(bool) ($y['ok'] ?? false)) {
        return [
            'allowed' => false,
            'status_code' => 'REJECTED_MODERN_YALLOP_COMPUTE_FAILED',
            'reason' => (string) ($y['status'] ?? 'Yallop calculation failed'),
            'metrics' => $y,
        ];
    }

    $q = (float) ($y['q'] ?? -99.0);
    $arcl = (float) ($y['arcl_deg'] ?? 0.0);
    $belowDanjon = (bool) ($y['danjon_guard_condition_met'] ?? false);
    $rejectDanjon = $applyDanjonGuard && $belowDanjon;
    $passesModernYallop = chandra_yallop_passes($q, $minCat, $rejectDanjon) && (($y['is_waxing'] ?? false) === true);

    // --- Classical Gate 1: SS 10.1 Ecliptic Separation ≥ 12° ---
    $waxingSepDeg = (float) $day['snapshot_elong_deg'];
    $ss10_1_Passed = $waxingSepDeg >= HYBRID_SS10_1_ECLIPTIC_MIN_DEG;

    // --- Classical Gates 2, 3, 4: Dharma Sindhu & Nirṇayāmṛta ---
    $classicalGates = $evaluateClassicalHybridGates($date, $day, $prevDay, $nextDay);
    $ds3AparahnaPassed = $classicalGates['dharma_sindhu']['ds_3_muhurta_aparahna_passed'];
    $ds6PradoshaPassed = $classicalGates['dharma_sindhu']['ds_6_muhurta_pradosha_passed'];
    $isKsayaPratipada = $classicalGates['nirnayamrita']['is_ksaya_pratipada'];

    $metrics = array_merge([
        'date' => (string) $day['date'],
        'modern_yallop' => $y,
        'ss10_1' => [
            'waxing_ecliptic_separation_deg' => round($waxingSepDeg, 4),
            'threshold_deg' => HYBRID_SS10_1_ECLIPTIC_MIN_DEG,
            'passed' => $ss10_1_Passed,
        ],
        'dharma_sindhu' => $classicalGates['dharma_sindhu'],
        'nirnayamrita' => $classicalGates['nirnayamrita'],
    ], $y);

    // --- Combined Decision Logic ---
    if (!$passesModernYallop) {
        return [
            'allowed' => false,
            'status_code' => $rejectDanjon ? 'REJECTED_DANJON_GUARD' : 'REJECTED_MODERN_YALLOP_Q_BELOW_THRESHOLD',
            'reason' => sprintf('Modern Yallop q=%.4f (cat %s) failed threshold or Danjon guard.', $q, $y['q_category'] ?? '?'),
            'metrics' => $metrics,
        ];
    }

    if (!$ss10_1_Passed) {
        return [
            'allowed' => false,
            'status_code' => 'REJECTED_SS10_1_BELOW_12_DEG',
            'reason' => sprintf('SS 10.1 failed: Waxing ecliptic separation %.2f° < 12.0° floor.', $waxingSepDeg),
            'metrics' => $metrics,
        ];
    }

    if ($isKsayaPratipada) {
        return [
            'allowed' => false,
            'status_code' => 'DEFERRED_NIRNAYAMRITA_KSAYA_PRATIPADA',
            'reason' => 'Nirṇayāmṛta mandate: Kṣaya Pratipadā detected on Day 1; observation deferred to Day 2.',
            'metrics' => $metrics,
        ];
    }

    $classicalDharmaSindhuPassed = $ds3AparahnaPassed || $ds6PradoshaPassed;
    if (!$classicalDharmaSindhuPassed) {
        return [
            'allowed' => false,
            'status_code' => 'REJECTED_DHARMA_SINDHU_GATES',
            'reason' => 'Dharma Sindhu failed: Neither 3-muhūrta Aparāhṇa nor 6-muhūrta Pradoṣa Dvitīyā criteria met.',
            'metrics' => $metrics,
        ];
    }

    return [
        'allowed' => true,
        'status_code' => 'SUCCESS_HYBRID_RESOLVED',
        'reason' => sprintf(
            'Hybrid Engine Success: Modern Yallop q=%.4f (cat %s) + SS 10.1 (%.2f° ≥ 12°) + Dharma Sindhu (Aparāhṇa: %s, Pradoṣa: %s) passed.',
            $q,
            $y['q_category'] ?? '?',
            $waxingSepDeg,
            $ds3AparahnaPassed ? 'YES' : 'NO',
            $ds6PradoshaPassed ? 'YES' : 'NO'
        ),
        'metrics' => $metrics,
        'selection_mode' => 'HYBRID_MODERN_PLUS_CLASSICAL',
    ];
};

$resolveSeasonHybrid = static function (array $season) use (
    $rangeEnd,
    $dayBundle,
    $evaluateEveningHybrid,
    $options
): array {
    $anchor = CarbonImmutable::parse((string) $season['anchor_date'], (string) $options['tz']);
    $amavasyaEnd = (float) $season['amavasya_end_jd'];
    $rejected = [];
    $allowed = [];

    for ($i = 0; $i < HYBRID_MAX_POST_AMAVASYA_EVENINGS; $i++) {
        $date = $anchor->addDays($i);
        if ($date->greaterThan($rangeEnd->addDays(2))) {
            break;
        }

        $day = $dayBundle($date);
        $prevDay = $dayBundle($date->subDay());
        $nextDay = $dayBundle($date->addDay());

        $eval = $evaluateEveningHybrid($day, $prevDay, $nextDay, $amavasyaEnd, $date);

        $record = [
            'date' => (string) $day['date'],
            'status_code' => (string) $eval['status_code'],
            'reason' => (string) $eval['reason'],
            'selection_mode' => $eval['selection_mode'] ?? null,
            'metrics' => $eval['metrics'],
        ];

        if (!(bool) $eval['allowed']) {
            $rejected[] = $record;
            continue;
        }

        $allowed[] = $record;
    }

    if ($allowed === []) {
        return [
            'status' => 'UNRESOLVED',
            'canonical_date' => null,
            'amavasya_end_jd' => $amavasyaEnd,
            'anchor_date' => (string) $season['anchor_date'],
            'selected' => null,
            'rejected' => $rejected,
            'reason_code' => 'NO_EVENING_PASSED_HYBRID_GATES',
        ];
    }

    usort($allowed, static fn (array $a, array $b): int => strcmp((string) $a['date'], (string) $b['date']));
    $selected = $allowed[0];

    return [
        'status' => 'RESOLVED',
        'canonical_date' => (string) $selected['date'],
        'amavasya_end_jd' => $amavasyaEnd,
        'anchor_date' => (string) $season['anchor_date'],
        'selected' => $selected,
        'rejected' => $rejected,
        'reason_code' => (string) $selected['status_code'],
        'selection_mode' => 'HYBRID_MODERN_PLUS_CLASSICAL',
    ];
};

$seasons = $discoverConjunctionSeasons();
$rows = [];
$dates = [];

foreach ($seasons as $season) {
    $resolved = $resolveSeasonHybrid($season);
    $can = $resolved['canonical_date'] ?? null;
    if ($can !== null) {
        $dates[] = (string) $can;
    }
    $rows[] = $resolved;
}

$dates = array_values(array_unique($dates));
sort($dates);

$payload = [
    'generated_at' => CarbonImmutable::now((string) $options['tz'])->toIso8601String(),
    'engine' => 'chandra_darshana_hybrid_engine',
    'description' => 'Combines 100% Modern Yallop TN69 Astronomical Model + SS 10.1 (≥12°) + Dharma Sindhu (3-muhūrta Aparāhṇa & 6-muhūrta Pradoṣa) + Nirṇayāmṛta (Kṣaya Pratipadā Day-2 Deferral).',
    'range' => [
        'from' => $rangeStart->toDateString(),
        'to' => $rangeEnd->toDateString(),
    ],
    'observer' => [
        'latitude' => (float) $options['lat'],
        'longitude' => (float) $options['lon'],
        'elevation_m' => (float) $options['elevation'],
        'timezone' => (string) $options['tz'],
    ],
    'gates_included' => [
        'modern_layer' => '100% Yallop TN69 (ARCL/ARCV geocentric, W′ topocentric, binary64 q)',
        'ss10_1' => 'Ecliptic waxing separation ≥ 12.0° at local sunset',
        'dharma_sindhu_aparahna' => 'Dvitīyā presence in Aparāhṇa window ≥ 3 muhūrtas',
        'dharma_sindhu_pradosha' => 'Dvitīyā presence in Pradoṣa window ≥ 6 muhūrtas',
        'nirnayamrita' => 'Strict Kṣaya Pratipadā Day-2 deferral mandate',
    ],
    'counts' => [
        'seasons_processed' => count($rows),
        'resolved_dates' => count($dates),
    ],
    'dates' => $dates,
    'rows' => $rows,
];

$outDir = $baseDir . '/scripts/output/experimental';
if (!is_dir($outDir)) {
    mkdir($outDir, 0777, true);
}
$outputPath = $options['output'] ?? sprintf(
    '%s/chandra_hybrid_%s_to_%s.json',
    $outDir,
    $rangeStart->toDateString(),
    $rangeEnd->toDateString()
);

panchang_script_write_json($outputPath, $payload);

echo "Written:\n{$outputPath}\n\n";
echo sprintf(
    "Hybrid Engine Resolved %d dates across %d seasons [%s to %s]\n\n",
    count($dates),
    count($rows),
    $rangeStart->toDateString(),
    $rangeEnd->toDateString()
);

$n = 0;
foreach ($rows as $row) {
    if (($row['canonical_date'] ?? null) === null) {
        continue;
    }
    $n++;
    $m = $row['selected']['metrics'] ?? [];
    $y = $m['modern_yallop'] ?? [];
    echo sprintf(
        "%02d. ~%s -> %s | q=%s cat=%s SS10.1=%s° | Aparāhṇa=%s Pradoṣa=%s | %s\n",
        $n,
        (string) ($row['anchor_date'] ?? '?'),
        (string) $row['canonical_date'],
        isset($y['q']) ? sprintf('%+.4f', (float) $y['q']) : 'n/a',
        (string) ($y['q_category'] ?? '?'),
        isset($m['ss10_1']['waxing_ecliptic_separation_deg']) ? sprintf('%.2f', (float) $m['ss10_1']['waxing_ecliptic_separation_deg']) : 'n/a',
        ($m['dharma_sindhu']['ds_3_muhurta_aparahna_passed'] ?? false) ? 'YES' : 'NO',
        ($m['dharma_sindhu']['ds_6_muhurta_pradosha_passed'] ?? false) ? 'YES' : 'NO',
        (string) ($row['reason_code'] ?? 'OK')
    );
}