#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Modern-only first-crescent resolver — Yallop 1997 q (TN 69 conventions).
 *
 * Coordinate conventions (TN 69):
 *   - ARCL, ARCV, DAZ  = geocentric
 *   - W′               = topocentric crescent width only
 *   - Tb               = sunset + (4/9)×lag
 *   - q                = (ARCV − poly(W′)) / 10
 *
 * Date selection (application policy, not a sighting guarantee):
 *   - post-conjunction sunset, positive lag
 *   - directed ecliptic waxing (Moon−Sun in (0°,180°))
 *   - raw binary64 q > min category floor (default B: q > −0.014)
 *   - application-level Danjon ARCL≥7° hard guard (default ON; --no-danjon-guard;
 *     does not inject q=−99 into the polynomial)
 *
 * Not used: fixed 10° / 39 min, classical SS gates.
 *
 * Output date is earliest_geometrically_supported_evening_by_yallop;
 * actual observation is not guaranteed (atmosphere / site).
 */
$baseDir = is_file(__DIR__ . '/../vendor/autoload.php') ? dirname(__DIR__) : __DIR__;
require $baseDir . '/vendor/autoload.php';
require __DIR__ . '/output_helpers.php';
require __DIR__ . '/lib/chandra_yallop.php';

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Traits\CliBootstrap;
use JmeEph\FFI\JmeEphFFI;

const MODERN_MAX_POST_CONJUNCTION_EVENINGS = 6;

$parseOptions = static function (array $argv): array {
    $options = [
        'from' => '2023-01-01',
        'to' => '2029-12-31',
        'lat' => 23.2472446,
        'lon' => 69.668339,
        'tz' => 'Asia/Kolkata',
        'elevation' => 0.0,
        'calendar_type' => 'amanta',
        /** Yallop: A/B/C/D — default B (q > -0.014) */
        'min_category' => 'B',
        /** Application-level Danjon ARCL≥7° hard guard (default enabled). */
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
  php scripts/chandra_modern_only_resolver.php --from=2024-01-01 --to=2028-12-31 --amanta

Modern Yallop q-criterion first-crescent dates (no 10°/39 min, no classical gates).

Options:
  --from= --to= --lat= --lon= --tz= --elevation=
  --amanta|--purnimanta
  --min-category=A|B|C|D   Accept evenings with q better than this band floor
                           A: q>0.216  B: q>-0.014 (default)
                           C: q>-0.160 D: q>-0.232
  --danjon-guard           Enable ARCL≥7° application hard guard (default)
  --no-danjon-guard        Disable Danjon hard guard (q still computed fully)
  --output=PATH

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

$jme = new JmeEphFFI;
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
        false,
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

$discoverConjunctionSeasons = static function () use ($rangeStart, $rangeEnd, $dayBundle): array {
    $padStart = $rangeStart->subDays(3);
    $padEnd = $rangeEnd->addDays(MODERN_MAX_POST_CONJUNCTION_EVENINGS + 2);
    $seasons = [];

    for ($d = $padStart; $d->lessThanOrEqualTo($padEnd); $d = $d->addDay()) {
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

$evaluateEvening = static function (array $day, float $amavasyaEndJd) use ($jme, $options): array {
    $sunset = (float) $day['sunset_jd'];
    $moonset = $day['moonset_jd'];
    $lat = (float) $options['lat'];
    $lon = (float) $options['lon'];
    $elev = (float) $options['elevation'];
    $minCat = (string) $options['min_category'];
    $applyDanjonGuard = (bool) $options['apply_danjon_guard'];

    // Strict: civil sunset must be after Amāvasyā end (conjunction).
    if ($sunset <= $amavasyaEndJd) {
        return [
            'allowed' => false,
            'status_code' => 'REJECTED_BEFORE_CONJUNCTION_END',
            'reason' => 'Sunset at or before Amāvasyā end.',
            'metrics' => [
                'date' => (string) $day['date'],
                'sunset_jd' => $sunset,
                'amavasya_end_jd' => $amavasyaEndJd,
            ],
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
            'reason' => 'Moonset not after sunset.',
            'metrics' => [
                'date' => (string) $day['date'],
                'lag_days' => $lagDays,
                'lag_minutes' => $lagDays * 1440.0,
            ],
        ];
    }

    // Yallop TN69: geocentric ARCL/ARCV/DAZ, topocentric W′ only, published q poly.
    // Elevation does not enter q (audit-only topo path). No 10°/39 min gates.
    $y = chandra_yallop_evaluate_evening($jme, $sunset, $moonsetF, $lat, $lon, $elev);
    $metrics = array_merge([
        'date' => (string) $day['date'],
        'illumination_percent_snapshot' => (float) $day['illumination_percent'],
        'snapshot_elong_deg' => (float) $day['snapshot_elong_deg'],
        'lunar_age_hours_at_sunset' => ($sunset - $amavasyaEndJd) * 24.0,
    ], $y);

    if (!(bool) ($y['ok'] ?? false)) {
        return [
            'allowed' => false,
            'status_code' => 'REJECTED_YALLOP_COMPUTE_FAILED',
            'reason' => (string) ($y['status'] ?? 'yallop_failed'),
            'metrics' => $metrics,
        ];
    }

    if (($y['status'] ?? '') === 'NOT_WAXING' || ($y['is_waxing'] ?? true) === false) {
        return [
            'allowed' => false,
            'status_code' => 'REJECTED_NOT_WAXING',
            'reason' => sprintf(
                'Directed ecliptic Moon−Sun separation=%s° not in open waxing half (0°, 180°) at T_best.',
                isset($y['directed_ecliptic_sep_deg'])
                    ? sprintf('%.17g', (float) $y['directed_ecliptic_sep_deg'])
                    : 'n/a',
            ),
            'metrics' => $metrics,
        ];
    }

    if (($y['q'] ?? null) === null || !is_finite((float) $y['q'])) {
        return [
            'allowed' => false,
            'status_code' => 'REJECTED_YALLOP_NO_Q',
            'reason' => (string) ($y['status'] ?? 'no_q'),
            'metrics' => $metrics,
        ];
    }

    $q = (float) $y['q'];
    $belowDanjon = (bool) ($y['danjon_guard_condition_met']
        ?? $y['danjon']['condition_met']
        ?? $y['danjon_guard_applied']
        ?? false);
    $rejectDanjon = $applyDanjonGuard && $belowDanjon;
    $qMargin = chandra_yallop_q_boundary_margin($q, $minCat);
    $metrics['apply_danjon_guard'] = $applyDanjonGuard;
    $metrics['danjon_guard_enabled'] = $applyDanjonGuard;
    $metrics['danjon_guard_configurable'] = true;
    $metrics['danjon_guard_rejected_this_evening'] = $rejectDanjon;
    $metrics['danjon_guard_condition_met'] = $belowDanjon;
    $metrics['q_boundary_margin'] = $qMargin;
    $metrics['coordinate_consistency_sensitivity'] =
        chandra_yallop_coordinate_consistency_sensitivity($qMargin);
    // Keep q-category pure; attach resolver Danjon policy separately.
    $metrics['danjon'] = array_merge(
        is_array($y['danjon'] ?? null) ? $y['danjon'] : [
            'condition_met' => $belowDanjon,
            'threshold_deg' => (float) ($y['danjon_guard_threshold_deg'] ?? 7.0),
            'part_of_q_polynomial' => false,
            'is_application_policy' => true,
        ],
        [
            'enabled_by_resolver' => $applyDanjonGuard,
            'rejected_this_evening' => $rejectDanjon,
        ],
    );
    // Display aliases stay q-derived (never forced to F by Danjon).
    $metrics['q_category'] = (string) ($y['q_category'] ?? $y['category_from_q'] ?? $y['category'] ?? '?');
    $metrics['q_label'] = (string) ($y['q_label'] ?? $y['label_from_q'] ?? $y['label'] ?? '?');
    $metrics['category'] = $metrics['q_category'];
    $metrics['label'] = $metrics['q_label'];

    if ($rejectDanjon) {
        return [
            'allowed' => false,
            'status_code' => 'REJECTED_DANJON_GUARD',
            'reason' => sprintf(
                'Application Danjon hard guard: geocentric ARCL=%s° < %s° (q=%s category_from_q=%s kept; guard is not a q-polynomial step). Use --no-danjon-guard to disable.',
                sprintf('%.17g', (float) ($y['arcl_deg'] ?? 0.0)),
                sprintf('%.17g', (float) ($y['danjon']['threshold_deg'] ?? $y['danjon_guard_degrees'] ?? 7.0)),
                isset($y['computed_q']) ? sprintf('%.17g', (float) $y['computed_q']) : sprintf('%.17g', $q),
                $metrics['q_category'],
            ),
            'metrics' => $metrics,
        ];
    }

    // Raw binary64 compare — q is never rounded before this test.
    if (!chandra_yallop_passes($q, $minCat, false)) {
        return [
            'allowed' => false,
            'status_code' => 'REJECTED_YALLOP_BELOW_MIN_CATEGORY',
            'reason' => sprintf(
                'Yallop q=%s q_category %s (%s) does not meet min category %s (require q>%s; margin=%s). ARCL/ARCV geocentric, W′ topocentric.',
                sprintf('%.17g', $q),
                $metrics['q_category'],
                $metrics['q_label'],
                $minCat,
                sprintf('%.17g', chandra_yallop_min_q_for_category($minCat)),
                sprintf('%.17g', $qMargin),
            ),
            'metrics' => $metrics,
        ];
    }

    return [
        'allowed' => true,
        'status_code' => 'SUCCESS_YALLOP_Q',
        'reason' => sprintf(
            'Yallop q=%s q_category %s (%s) > min %s (margin=%s); ARCL_geo=%s° ARCV_geo=%s° W′=%s′ lag=%s min (TN69; not 10°/39).',
            sprintf('%.17g', $q),
            $metrics['q_category'],
            $metrics['q_label'],
            $minCat,
            sprintf('%.17g', $qMargin),
            sprintf('%.17g', (float) ($y['arcl_deg'] ?? 0.0)),
            sprintf('%.17g', (float) ($y['arcv_deg'] ?? 0.0)),
            sprintf('%.17g', (float) ($y['w_prime_arcmin'] ?? $y['width_arcmin'] ?? 0.0)),
            sprintf('%.17g', $lagDays * 1440.0),
        ),
        'metrics' => $metrics,
        'selection_mode' => 'YALLOP_Q_TN69',
    ];
};

$resolveSeason = static function (array $season) use (
    $rangeEnd,
    $dayBundle,
    $evaluateEvening,
    $options,
): array {
    $anchor = CarbonImmutable::parse(
        (string) $season['anchor_date'],
        (string) $options['tz'],
    );
    $amavasyaEnd = (float) $season['amavasya_end_jd'];
    $rejected = [];
    $allowed = [];

    for ($i = 0; $i < MODERN_MAX_POST_CONJUNCTION_EVENINGS; $i++) {
        $date = $anchor->addDays($i);
        if ($date->greaterThan($rangeEnd->addDays(1))) {
            break;
        }
        $day = $dayBundle($date);
        $eval = $evaluateEvening($day, $amavasyaEnd);
        $record = [
            'date' => (string) $day['date'],
            'status_code' => (string) $eval['status_code'],
            'reason' => (string) $eval['reason'],
            'metrics' => $eval['metrics'],
            'selection_mode' => $eval['selection_mode'] ?? null,
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
            'reason_code' => 'NO_EVENING_PASSED_YALLOP',
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
        'selection_mode' => 'YALLOP_Q_TN69',
    ];
};

$seasons = $discoverConjunctionSeasons();
$rows = [];
$dates = [];

foreach ($seasons as $season) {
    $resolved = $resolveSeason($season);
    $can = $resolved['canonical_date'] ?? null;
    if ($can === null) {
        $anchor = CarbonImmutable::parse(
            (string) $season['anchor_date'],
            (string) $options['tz'],
        );
        if ($anchor->lessThan($rangeStart) || $anchor->greaterThan($rangeEnd)) {
            continue;
        }
        $rows[] = $resolved;
        continue;
    }
    $canC = CarbonImmutable::parse((string) $can, (string) $options['tz']);
    if ($canC->lessThan($rangeStart) || $canC->greaterThan($rangeEnd)) {
        continue;
    }
    $dates[] = (string) $can;
    $rows[] = $resolved;
}

$dates = array_values(array_unique($dates));
sort($dates);

$payload = [
    'generated_at' => CarbonImmutable::now((string) $options['tz'])->toIso8601String(),
    'model' => 'yallop_1997_q_tn69_geocentric_arcl_arcv_topocentric_w_prime',
    'description' => 'earliest_geometrically_supported_evening_by_yallop: post-Amāvasyā evening with raw binary64 Yallop q above min category. TN 69: geocentric ARCL/ARCV/DAZ, topocentric W′ only, Tb=sunset+(4/9)lag. Not a naked-eye guarantee.',
    'range' => [
        'from' => $rangeStart->toDateString(),
        'to' => $rangeEnd->toDateString(),
    ],
    'observer' => [
        'latitude' => (float) $options['lat'],
        'longitude' => (float) $options['lon'],
        'elevation_m' => (float) $options['elevation'],
        'timezone' => (string) $options['tz'],
        'observer_model_for_q' => 'geocentric_horizon_at_geodetic_latitude',
        'elevation_used_in_yallop_q' => false,
        'elevation_used_in_optional_topocentric_audit' => true,
        'wgs84_topocentric_moon_audit_only' => true,
    ],
    'calendar_type' => (string) $options['calendar_type'],
    'application_policy' => [
        'public_date_selection_is_application_policy' => true,
        'actual_observation_guaranteed' => false,
        'date_field_meaning' => 'earliest_geometrically_supported_evening_by_yallop',
        'category_B_means' => 'visible_under_perfect_atmospheric_conditions_not_guaranteed_sighting',
        'danjon_guard_is_application_policy' => true,
        'danjon_guard_threshold_deg' => 7.0,
        'danjon_guard_is_part_of_q_polynomial' => false,
        'danjon_guard_enabled' => (bool) $options['apply_danjon_guard'],
        'danjon_guard_configurable' => true,
        'danjon_guard_cli' => '--danjon-guard | --no-danjon-guard',
    ],
    'gates' => [
        'post_conjunction_sunset_strict' => true,
        'positive_moonset_lag_strict' => true,
        'waxing_directed_ecliptic_moon_minus_sun' => true,
        'yallop_q_raw_binary64' => true,
        'danjon_guard_arcl_geocentric_lt_7' => (bool) $options['apply_danjon_guard'],
        'danjon_guard_enabled' => (bool) $options['apply_danjon_guard'],
        'danjon_guard_configurable' => true,
        'danjon_does_not_inject_q_sentinel' => true,
        'danjon_guard_is_application_policy' => true,
        'danjon_guard_is_part_of_q_polynomial' => false,
        'min_category' => (string) $options['min_category'],
        'min_q' => chandra_yallop_min_q_for_category((string) $options['min_category']),
        'best_time' => 'sunset + (4/9)*lag (TN 69 eq 4.1)',
        'arcl' => 'geocentric',
        'arcv' => 'geocentric_airless',
        'w_prime' => 'SD_prime_times_1_minus_cos_ARCL_geo_with_SD_equals_0.27245_times_pi',
        'sd_formula' => 'SD = 0.27245 * arcsin(a/Δ)  [TN69 3.8]',
        'selection' => 'earliest_evening_passing_yallop',
        'reference' => 'Yallop_HMNAO_TN_69_1997',
        'decision_rounding' => false,
        'jme_sidereal_time_convention' => 'GMST',
        'equatorial_coordinate_frame' => 'mean_equator_equinox_of_date',
        'equatorial_nutation_applied' => false,
        'coordinate_pair' => 'mean_RA_Dec_plus_GMST',
        'coordinate_time_consistency_verified' => true,
        'q_boundary_near_threshold' => 0.001,
        'q_boundary_flag_purpose' => 'numerical_and_ephemeris_sensitivity_audit',
        'arcl_consistency_is_tn69_eq_2_1' => false,
        'removed_heuristics' => [
            'fixed_10_deg_elongation',
            'fixed_39_min_lag',
            'score_0_75_0_25_as_date_gate',
            'topocentric_arcl_arcv_as_yallop_inputs',
            'unsigned_pheno_elong_as_waxing',
            'q_equals_minus_99_sentinel',
        ],
        'not_used' => [
            'surya_siddhanta_12_bhaga',
            'ss10_lagnantara',
            'classical_six_muhurta_aparahna',
            'drik_empirical_10_39',
        ],
    ],
    'counts' => [
        'seasons_in_output' => count($rows),
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
    '%s/chandra_modern_yallop_fullprec_%s_to_%s.json',
    $outDir,
    $rangeStart->toDateString(),
    $rangeEnd->toDateString(),
);
panchang_script_write_json($outputPath, $payload);

echo "Written:\n{$outputPath}\n\n";
echo sprintf(
    "Yallop TN69 min-category %s (q>%s) danjon=%s | %s → %s | %d dates\n\n",
    (string) $options['min_category'],
    sprintf('%.17g', chandra_yallop_min_q_for_category((string) $options['min_category'])),
    (bool) $options['apply_danjon_guard'] ? 'ON' : 'OFF',
    $rangeStart->toDateString(),
    $rangeEnd->toDateString(),
    count($dates),
);

$n = 0;
foreach ($rows as $row) {
    if (($row['canonical_date'] ?? null) === null) {
        continue;
    }
    $n++;
    $m = $row['selected']['metrics'] ?? [];
    // Console display only — decisions already used full binary64 metrics.
    echo sprintf(
        "%02d. ~%s -> %s | q=%s cat=%s ARCL=%s° ARCV=%s° W=%s′ lag=%s min\n",
        $n,
        (string) ($row['anchor_date'] ?? '?'),
        (string) $row['canonical_date'],
        isset($m['q']) && is_numeric($m['q']) ? sprintf('%+.12f', (float) $m['q']) : 'n/a',
        (string) ($m['q_category'] ?? $m['category'] ?? '?'),
        isset($m['arcl_deg']) ? sprintf('%.10f', (float) $m['arcl_deg']) : 'n/a',
        isset($m['arcv_deg']) ? sprintf('%.10f', (float) $m['arcv_deg']) : 'n/a',
        isset($m['width_arcmin']) ? sprintf('%.10f', (float) $m['width_arcmin']) : 'n/a',
        isset($m['lag_minutes']) ? sprintf('%.10f', (float) $m['lag_minutes']) : 'n/a',
    );
}