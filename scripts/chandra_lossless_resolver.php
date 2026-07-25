#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Standalone monthly Chandra Darśana reference classifier.
 *
 * Date priority (default — SS10 lag is diagnostic only):
 *   1) Sūrya Siddhānta 12-bhāga modern proxy (Δλ ≥ 12° at sunset) + horizon.
 *   2) Classical nibandha Dvitīyā sāmbhavya (6 muhūrtas AND Aparāhṇa).
 *   3) Modern visibility_proxy_score (opt-in --legacy-modern-proxy-date only).
 *
 * SS 10.2–10.4 inspired continuous lag (JME RA/Dec/lon/speeds + thin AD):
 *   always computed and reported as classical_time_interval_proxy;
 *   does NOT set the date unless --ss10-lag-gate (opt-in engineering policy).
 *   Not exact rāśimāna/cara tables; not full ch.10; not mandatory ≥720 asu.
 *
 * Candidate scope: Shukla Pratipadā / Dvitīyā field (source-attested tithis 1–2).
 */
$baseDir = is_file(__DIR__ . '/../vendor/autoload.php') ? dirname(__DIR__) : __DIR__;
require $baseDir . '/vendor/autoload.php';
require __DIR__ . '/output_helpers.php';
require __DIR__ . '/lib/chandra_ss10_oa.php';

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Traits\CliBootstrap;
use JmeEph\FFI\JmeEphFFI;

const CHANDRA_STANDARD_MONTHLY_VRATA = 'STANDARD_MONTHLY_VRATA';
const CHANDRA_GOVARDHAN_PUJA_FESTIVAL = 'GOVARDHAN_PUJA_FESTIVAL';
/**
 * Post-Amavasya evenings scanned; selection prefers earliest accepted date.
 * 5 covers extreme latitudes / delayed visibility; 4 is enough for typical India.
 */
const CHANDRA_MAX_POST_AMAVASYA_EVENINGS = 5;
/**
 * Hard lag reject only (engineering floor, not a classical/universal astronomy law).
 * Values above this still need elongation/proxy support — lag alone is not acceptance.
 */
const CHANDRA_MIN_LAG_MINUTES_HARD_REJECT = 5.0;
/** Reject numerically tiny crescents. */
const CHANDRA_MIN_ILLUMINATION_PERCENT = 0.5;
/**
 * Modern visibility-proxy score floor (diagnostic / legacy date path only).
 * Engineering threshold in 0.75·elongation + 0.25·lag space — not Yallop/Danjon.
 */
const CHANDRA_PROXY_ACCEPTANCE_THRESHOLD = -0.232;
/**
 * Modern operational proxy (degrees of directed ecliptic separation at sunset).
 *
 * SS 10.1 twelve-bhāga is textually disputed (ecliptic arc vs ascensional time /
 * 720 asu). This constant operationalizes a Reading-A-style modern proxy only.
 * It is NOT: full ch.10 recomputation, exact dṛkkarma, exact lagnāntarāsavaḥ,
 * or a fabricated final_asu>=720 gate (SS 10.2–10.4 state no such boolean).
 */
const CHANDRA_SURYA_SIDDHANTA_12_BHAGA_PROXY_DEG = 12.0;
/** Source-attested candidate tithis for monthly Chandra Darśana (Pratipadā, Dvitīyā). */
const CHANDRA_SOURCE_ATTESTED_TITHIS = [1, 2];
/** Minimum illumination (%) for Govardhan night-moon visibility proxy. */
const CHANDRA_NIGHT_MOON_MIN_ILLUMINATION_PERCENT = 2.0;
/** Coarse elongation support for Govardhan night-moon proxy (degrees). */
const CHANDRA_NIGHT_MOON_MIN_ELONGATION_DEG = 12.0;
/**
 * Opt-in SS10 lag date gate: minimum continuous lag proxy (asu).
 * Default 0 = require positive proxy lag only (not a textual ≥720 rule).
 * Gate itself is off by default — SS10 is diagnostic unless --ss10-lag-gate.
 */
const CHANDRA_SS10_MIN_LAG_ASU_DEFAULT = 0.0;

$parseOptions = static function (array $argv): array {
    $options = [
        'from' => '2024-01-01',
        'to' => '2028-12-31',
        'lat' => 23.2472446,
        'lon' => 69.668339,
        'tz' => 'Asia/Kolkata',
        'elevation' => 0.0,
        'calendar_type' => 'amanta',
        'profile' => CHANDRA_STANDARD_MONTHLY_VRATA,
        'allow_low_confidence_visibility' => false,
        /** When true, medium/high modern proxy alone may set the date (pre-scripture-first). */
        'legacy_modern_proxy_date' => false,
        /** Opt-in: allow SS10 continuous lag proxy to set date (default off = diagnostic only). */
        'ss10_lag_gate' => false,
        'ss10_min_lag_asu' => CHANDRA_SS10_MIN_LAG_ASU_DEFAULT,
        'strict_shastric_veto' => true,
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
        } elseif (str_starts_with((string) $arg, '--profile=')) {
            $options['profile'] = substr((string) $arg, 10);
        } elseif (str_starts_with((string) $arg, '--output=')) {
            $options['output'] = substr((string) $arg, 9);
        } elseif ($arg === '--purnimanta') {
            $options['calendar_type'] = 'purnimanta';
        } elseif ($arg === '--amanta') {
            $options['calendar_type'] = 'amanta';
        } elseif ($arg === '--allow-low-confidence-visibility' || $arg === '--allow-class-c-optical') {
            // Legacy alias: --allow-class-c-optical (pre-rename; not a Yallop class).
            $options['allow_low_confidence_visibility'] = true;
        } elseif ($arg === '--legacy-modern-proxy-date') {
            $options['legacy_modern_proxy_date'] = true;
        } elseif ($arg === '--ss10-lag-gate') {
            $options['ss10_lag_gate'] = true;
        } elseif ($arg === '--no-ss10-lag-gate') {
            $options['ss10_lag_gate'] = false;
        } elseif (str_starts_with((string) $arg, '--ss10-min-lag-asu=')) {
            $options['ss10_min_lag_asu'] = (float) substr((string) $arg, 19);
        } elseif ($arg === '--no-strict-shastric-veto') {
            $options['strict_shastric_veto'] = false;
        } elseif ($arg === '-h' || $arg === '--help') {
            $options['help'] = true;
        } else {
            throw new InvalidArgumentException('Unknown option: ' . $arg);
        }
    }

    return $options;
};

$usage = static fn (): string => <<<TEXT
Usage:
  php scripts/chandra_lossless_resolver.php --from=2024-01-01 --to=2028-12-31 --amanta

Options:
  --from=YYYY-MM-DD             Inclusive Gregorian start date
  --to=YYYY-MM-DD               Inclusive Gregorian end date
  --lat=FLOAT                   Observer latitude (default Bhuj)
  --lon=FLOAT                   Observer longitude (default Bhuj)
  --tz=TZ                       Observer timezone (default Asia/Kolkata)
  --elevation=FLOAT             Observer elevation meters
  --amanta|--purnimanta         Calendar type for snapshots
  --profile=STANDARD_MONTHLY_VRATA|GOVARDHAN_PUJA_FESTIVAL
  --allow-low-confidence-visibility  With --legacy-modern-proxy-date: accept low band
  --legacy-modern-proxy-date    Allow modern medium/high proxy alone to set the date
  --ss10-lag-gate               Opt-in: SS10 continuous lag proxy may set the date
  --no-ss10-lag-gate            SS10 diagnostic only (default)
  --ss10-min-lag-asu=FLOAT      With --ss10-lag-gate: min proxy asu (default 0; not textual 720)
  --no-strict-shastric-veto     Disable Govardhan night-moon veto
  --output=PATH                 JSON output path

Default date priority (SS10 lag is diagnostic only unless --ss10-lag-gate):
  1) Sūrya Siddhānta 12-bhāga proxy (≥12° at sunset) + horizon window
  2) Classical Dvitīyā sāmbhavya (6 muhūrtas AND Aparāhṇa)
  3) Modern proxy score only with --legacy-modern-proxy-date
  (opt-in) SS10 continuous lag proxy as engineering date gate

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

// JmeEph for SS 10 positions / equatorial / speeds (configureJme is private — reflection once).
$jme = new JmeEphFFI;
$configureJme = new ReflectionMethod(CliBootstrap::class, 'configureJme');
$configureJme->invoke(null, $jme);

/** @var array<string, array<string, mixed>> $dayBundleCache */
$dayBundleCache = [];

/** @var array<string, array<string, mixed>> $ss10Cache */
$ss10Cache = [];

$computeSs10AtSunset = static function (float $sunsetJd) use (
    $jme,
    $options,
    &$ss10Cache,
): array {
    $key = sprintf('%.8F|%.5F', $sunsetJd, (float) $options['lat']);
    if (isset($ss10Cache[$key])) {
        return $ss10Cache[$key];
    }

    return $ss10Cache[$key] = chandra_ss10_iterate_chapter10_jme(
        $jme,
        $sunsetJd,
        (float) $options['lat'],
    );
};

$normalizeDegrees = static function (float $degrees): float {
    $d = fmod($degrees, 360.0);

    return $d < 0.0 ? $d + 360.0 : $d;
};

$extractJd = static function (mixed $value): ?float {
    if (is_float($value) || is_int($value)) {
        return (float) $value;
    }

    if (is_array($value) && isset($value['jd'])) {
        return (float) $value['jd'];
    }

    return null;
};

$dayBundle = static function (CarbonImmutable $date) use (&$dayBundleCache, $service, $options, $extractJd, $normalizeDegrees): array {
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
    $sunriseJd = (float) ($ctx['sunrise_jd'] ?? 0.0);
    $sunsetJd = (float) ($ctx['sunset_jd'] ?? 0.0);
    $nextSunriseJd = (float) ($ctx['next_sunrise_jd'] ?? 0.0);

    return $dayBundleCache[$key] = [
        'date' => $key,
        'tithi_index_abs' => (int) ($ctx['tithi_index_abs'] ?? 0),
        'tithi_start_jd' => (float) ($ctx['tithi_start_jd'] ?? 0.0),
        'tithi_end_jd' => (float) ($ctx['tithi_end_jd'] ?? 0.0),
        'sunrise_jd' => $sunriseJd,
        'sunset_jd' => $sunsetJd,
        'next_sunrise_jd' => $nextSunriseJd,
        'day_muhurta_days' => $sunsetJd > $sunriseJd ? ($sunsetJd - $sunriseJd) / 15.0 : 0.0,
        'moonrise_jd' => $extractJd($details['Moonrise_JD'] ?? ($details['Moonrise'] ?? null)),
        'moonset_jd' => $extractJd($details['Moonset_JD'] ?? ($details['Moonset'] ?? null)),
        'waxing_separation_deg' => $normalizeDegrees((float) ($ctx['moon_sun_elongation_at_sunset_degrees'] ?? 0.0)),
        'illumination_percent' => (float) ($ctx['moon_illumination_at_sunset_percent'] ?? 0.0),
    ];
};

/**
 * Dvitīyā interval for classical Aparāhṇa / 6-muhūrta proxy.
 * Handles sunrise Amāvāsyā with kṣaya Pratipadā (Dvitīyā may begin same civil day).
 */
$dvitiyaIntervalFor = static function (CarbonImmutable $date, array $day) use ($dayBundle): array {
    $abs = (int) $day['tithi_index_abs'];

    if ($abs === 2) {
        return [
            'start_jd' => (float) $day['tithi_start_jd'],
            'end_jd' => (float) $day['tithi_end_jd'],
        ];
    }

    if ($abs === 1) {
        $next = $dayBundle($date->addDay());

        return [
            'start_jd' => (float) $day['tithi_end_jd'],
            'end_jd' => (int) $next['tithi_index_abs'] === 2
                ? (float) $next['tithi_end_jd']
                : max((float) $day['tithi_end_jd'], (float) $next['tithi_start_jd']),
        ];
    }

    // Kṣaya Pratipadā: sunrise Amāvāsyā (abs 30); next sunrise already Dvitīyā.
    if ($abs === 30) {
        $next = $dayBundle($date->addDay());
        if ((int) $next['tithi_index_abs'] === 2) {
            return [
                'start_jd' => (float) $next['tithi_start_jd'],
                'end_jd' => (float) $next['tithi_end_jd'],
            ];
        }
    }

    return ['start_jd' => 0.0, 'end_jd' => 0.0];
};

$discoverPostAmavasyaSeasons = static function () use ($rangeStart, $rangeEnd, $dayBundle): array {
    $seasons = [];

    for ($d = $rangeStart; $d->lessThanOrEqualTo($rangeEnd); $d = $d->addDay()) {
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
            if ($anchor >= $rangeStart->toDateString()) {
                $seasons[sprintf('%.5F', (float) $day['tithi_start_jd'])] = [
                    'amavasya_end_jd' => (float) $day['tithi_start_jd'],
                    'anchor_date' => $anchor,
                ];
            }
        }
    }

    ksort($seasons);

    return array_values($seasons);
};

/**
 * Deterministic visibility proxy only (not Yallop q).
 * Elongation + lag only; illumination is a separate hard sanity gate (correlated with elong).
 */
$estimateVisibilityProxyScore = static function (array $candidate): float {
    $lagMinutes = (float) ($candidate['lag_minutes'] ?? 0.0);
    $elong = (float) $candidate['waxing_separation_deg'];

    return 0.75 * (($elong - 10.0) / 20.0)
        + 0.25 * (($lagMinutes - 20.0) / 60.0);
};

/** Proxy confidence bands — model confidence only, not proven sky visibility. */
$proxyBandFor = static function (float $score): string {
    if ($score > 0.216) {
        return 'PROXY_HIGH_CONFIDENCE';
    }

    if ($score > -0.014) {
        return 'PROXY_MEDIUM_CONFIDENCE';
    }

    if ($score > -0.100) {
        return 'PROXY_LOW_CONFIDENCE';
    }

    // Between low-confidence band and acceptance threshold (still classically usable).
    if ($score > CHANDRA_PROXY_ACCEPTANCE_THRESHOLD) {
        return 'PROXY_BORDERLINE';
    }

    return 'PROXY_REJECTED';
};

$isScripturePrimarySuccess = (static fn (string $statusCode): bool => in_array($statusCode, [
    'SUCCESS_SS10_LAG_PROXY_ENGINEERING_POLICY',
    'SUCCESS_SS10_ITERATED_LAGNANTARA_LAG', // legacy alias if any cached outputs
    'SUCCESS_SURYA_SIDDHANTA_12_BHAGA_PROXY',
], true));

$isClassicalSuccess = (static fn (string $statusCode): bool => $statusCode === 'SUCCESS_SHASTRIC_PROXY_SAMBHAVYA'
    || $statusCode === 'SUCCESS_CLASSICAL_DVITIYA_SAMBHAVYA');

$isModernProxyDateSuccess = (static fn (string $statusCode): bool => in_array($statusCode, [
    'SUCCESS_PROXY_HIGH_CONFIDENCE',
    'SUCCESS_PROXY_MEDIUM_CONFIDENCE',
    'SUCCESS_PROXY_LOW_CONFIDENCE_VISIBILITY',
], true));

/** @deprecated use isModernProxyDateSuccess — kept name for older call sites */
$isAstronomicalSuccess = $isModernProxyDateSuccess;

/** @var array<string, int> $tithiAbsAtJdCache */
$tithiAbsAtJdCache = [];

/**
 * Absolute tithi 1–30 at Julian day via panchang-core Current_Tithi_At_Input_Now
 * (ephemeris-backed; handles kṣaya/vriddhi, not sunrise-index +1).
 */
$tithiIndexAbsAtJd = static function (float $jd) use (
    $service,
    $options,
    &$tithiAbsAtJdCache,
): int {
    // Key by JD + observer (supports multi-location runs in one process).
    $cacheKey = sprintf(
        '%.6F|%.6F|%.6F|%s|%s',
        $jd,
        (float) $options['lat'],
        (float) $options['lon'],
        (string) $options['tz'],
        (string) $options['calendar_type'],
    );
    if (isset($tithiAbsAtJdCache[$cacheKey])) {
        return $tithiAbsAtJdCache[$cacheKey];
    }

    $unix = ($jd - 2440587.5) * 86400.0;
    $at = CarbonImmutable::createFromTimestampUTC((int) round($unix))
        ->setTimezone((string) $options['tz']);
    $civilDate = $at->startOfDay();

    $tithi = $service->getCurrentTithi(
        $civilDate,
        (float) $options['lat'],
        (float) $options['lon'],
        (string) $options['tz'],
        $at,
        (float) $options['elevation'],
        (string) $options['calendar_type'],
    );

    // getCurrentTithi index is absolute 1–30 in this engine (Krishna continues 16–30).
    $abs = (int) ($tithi['index'] ?? 0);
    if ($abs < 1 || $abs > 30) {
        $abs = 0;
    }

    return $tithiAbsAtJdCache[$cacheKey] = $abs;
};

$evaluateSingleCandidate = static function (
    ?array $candidate,
    string $profile,
    bool $allowLowConfidenceVisibility,
    bool $strictShastricVeto,
    bool $legacyModernProxyDate = false,
    bool $ss10LagGate = true,
    float $ss10MinLagAsu = CHANDRA_SS10_MIN_LAG_ASU_DEFAULT,
) use ($estimateVisibilityProxyScore, $proxyBandFor, $tithiIndexAbsAtJd, $computeSs10AtSunset): array {
    if ($candidate === null) {
        return [
            'status_code' => 'REJECTED_MALFORMED_CANDIDATE',
            'allowed' => false,
            'selection_mode' => null,
            'computed_value' => null,
            'reason' => 'Candidate object is null.',
            'tier' => 'T3',
        ];
    }

    // moonset_jd may be legitimately null (no moonset on civil day) — not a missing-key config error.
    foreach ([
        'date_str',
        'sunrise_jd',
        'sunset_jd',
        'next_sunrise_jd',
        'dvitiya_start_jd',
        'dvitiya_end_jd',
        'waxing_separation_deg',
        'illumination_percent',
        'tithi_index_abs_at_sunrise',
    ] as $key) {
        if (!array_key_exists($key, $candidate) || $candidate[$key] === null) {
            return [
                'status_code' => 'REJECTED_MISSING_KEY_' . strtoupper($key),
                'allowed' => false,
                'selection_mode' => null,
                'computed_value' => null,
                'reason' => sprintf("Required candidate ephemeris key '%s' is missing or null.", $key),
                'tier' => 'T3',
            ];
        }
    }

    if ($candidate['moonset_jd'] === null) {
        return [
            'status_code' => 'REJECTED_MOONSET_UNAVAILABLE',
            'allowed' => false,
            'selection_mode' => null,
            'computed_value' => null,
            'reason' => 'Moonset JD unavailable (no civil-day moonset, polar geometry, or ephemeris gap); not a config-key error.',
            'tier' => 'T0',
        ];
    }

    $sunrise = (float) $candidate['sunrise_jd'];
    $sunset = (float) $candidate['sunset_jd'];
    $nextSunrise = (float) $candidate['next_sunrise_jd'];
    $moonset = (float) $candidate['moonset_jd'];
    $moonrise = isset($candidate['moonrise_jd']) && $candidate['moonrise_jd'] !== null
        ? (float) $candidate['moonrise_jd']
        : null;
    $dvitiyaStart = (float) $candidate['dvitiya_start_jd'];
    $dvitiyaEnd = (float) $candidate['dvitiya_end_jd'];
    $waxingSep = (float) $candidate['waxing_separation_deg'];
    $illumination = (float) $candidate['illumination_percent'];
    $dayMuhurta = $sunset > $sunrise ? ($sunset - $sunrise) / 15.0 : 0.0;
    // Aparāhṇa: 10th–12th daytime muhūrta (after 9 muhūrtas from sunrise).
    $aparahnaStart = $sunrise + (9.0 * $dayMuhurta);
    $aparahnaEnd = $sunrise + (12.0 * $dayMuhurta);
    $lagDays = $moonset - $sunset;
    $lagMinutes = $lagDays * 1440.0;
    $bestTimeJd = $sunset + (4.0 / 9.0) * max(0.0, $lagDays);

    $dvitiyaOverlap = max(0.0, min($sunset, $dvitiyaEnd) - max($sunrise, $dvitiyaStart));
    $dvitiyaDaylightMuhurtas = $dayMuhurta > 0.0 ? $dvitiyaOverlap / $dayMuhurta : 0.0;
    // True Aparāhṇa presence: Dvitīyā must overlap [aparahnaStart, aparahnaEnd].
    $dvitiyaActiveInAparahna = $dvitiyaStart > 0.0
        && $dvitiyaStart <= $aparahnaEnd + 1e-9
        && $dvitiyaEnd >= $aparahnaStart - 1e-9;
    $meetsSixMuhurtaRule = $dvitiyaDaylightMuhurtas >= 6.0;
    // Classical sāmbhavya: six daylight muhūrtas AND Aparāhṇa overlap.
    $classicalSambhavya = $meetsSixMuhurtaRule && $dvitiyaActiveInAparahna;
    $isWaxing = $waxingSep > 0.0 && $waxingSep < 180.0;
    $hasPositiveLag = $lagDays > 0.0;
    $moonsetAfterSunsetMinutes = $hasPositiveLag ? $lagMinutes : 0.0;

    // Night-moon *proxy* for Govardhan (altitude unavailable): not mere positive lag.
    // Prefer false acceptance over false rejection on the prohibition path.
    $nightMoonSupport = $hasPositiveLag
        && $illumination >= CHANDRA_NIGHT_MOON_MIN_ILLUMINATION_PERCENT
        && $waxingSep >= CHANDRA_NIGHT_MOON_MIN_ELONGATION_DEG;
    $nightMoonVisibleProxy = $nightMoonSupport;
    if ($moonrise !== null && $moonrise >= $sunset - 1e-9 && $moonrise < $nextSunrise - 1e-9
        && $illumination >= CHANDRA_NIGHT_MOON_MIN_ILLUMINATION_PERCENT
        && $waxingSep >= CHANDRA_NIGHT_MOON_MIN_ELONGATION_DEG) {
        $nightMoonVisibleProxy = true;
    }

    $visibilityProxyScore = $estimateVisibilityProxyScore($candidate + ['lag_minutes' => $lagMinutes]);
    $proxyBand = $proxyBandFor($visibilityProxyScore);
    $amavasyaEndJd = isset($candidate['amavasya_end_jd']) ? (float) $candidate['amavasya_end_jd'] : 0.0;
    $lunarAgeHours = $amavasyaEndJd > 0.0 ? ($bestTimeJd - $amavasyaEndJd) * 24.0 : null;

    // Evening event: resolve absolute tithi via engine at each JD (not sunrise+1).
    $tithiAtSunrise = (int) $candidate['tithi_index_abs_at_sunrise'];
    $tithiAtSunset = $tithiIndexAbsAtJd($sunset);
    $tithiAtBestTime = $tithiIndexAbsAtJd($bestTimeJd);
    if ($tithiAtSunrise < 1) {
        $tithiAtSunrise = $tithiIndexAbsAtJd($sunrise);
    }

    $proxyPayload = (static fn (array $extra = []): array => array_merge([
        'visibility_proxy_score' => round($visibilityProxyScore, 4),
        'proxy_band' => $proxyBand,
        'lag_minutes' => round($lagMinutes, 2),
        'moonset_after_sunset_minutes' => round($moonsetAfterSunsetMinutes, 2),
        'lunar_age_hours' => $lunarAgeHours !== null ? round($lunarAgeHours, 2) : null,
        'tithi_index_abs_at_sunrise' => $tithiAtSunrise,
        'tithi_index_abs_at_sunset' => $tithiAtSunset,
        'tithi_index_abs_at_best_visibility_time' => $tithiAtBestTime,
    ], $extra));

    if (!$isWaxing) {
        return [
            'status_code' => 'REJECTED_NOT_WAXING_CRESCENT',
            'allowed' => false,
            'selection_mode' => null,
            'computed_value' => $proxyPayload(),
            'reason' => 'Moon is in waning phase or pre-conjunction; waxing elongation must be in (0, 180) degrees.',
            'tier' => 'T0',
        ];
    }

    if (!$hasPositiveLag) {
        return [
            'status_code' => 'REJECTED_MOONSET_BEFORE_OR_WITH_SUNSET',
            'allowed' => false,
            'selection_mode' => null,
            'computed_value' => $proxyPayload(),
            'reason' => 'Moon sets before or simultaneously with sunset; lag must be strictly positive.',
            'tier' => 'T0',
        ];
    }

    if ($illumination < CHANDRA_MIN_ILLUMINATION_PERCENT) {
        return [
            'status_code' => 'REJECTED_ILLUMINATION_TOO_LOW',
            'allowed' => false,
            'selection_mode' => null,
            'computed_value' => $proxyPayload(['illumination_percent' => round($illumination, 4)]),
            'reason' => sprintf(
                'Illumination %.4f%% is below the %.2f%% sanity floor for a usable crescent proxy.',
                $illumination,
                CHANDRA_MIN_ILLUMINATION_PERCENT,
            ),
            'tier' => 'T0',
        ];
    }

    if ($profile === CHANDRA_GOVARDHAN_PUJA_FESTIVAL && $nightMoonVisibleProxy && $strictShastricVeto) {
        return [
            'status_code' => 'REJECTED_GOVARDHAN_NIGHT_MOON_VISIBLE_PROXY',
            'allowed' => false,
            'selection_mode' => null,
            'computed_value' => $proxyPayload([
                'night_moon_visible_proxy' => true,
                'night_moon_min_illumination_percent' => CHANDRA_NIGHT_MOON_MIN_ILLUMINATION_PERCENT,
                'night_moon_min_elongation_deg' => CHANDRA_NIGHT_MOON_MIN_ELONGATION_DEG,
                'illumination_percent' => round($illumination, 4),
                'waxing_separation_deg' => round($waxingSep, 2),
                'tithi_index_abs_at_sunrise' => (int) $candidate['tithi_index_abs_at_sunrise'],
            ]),
            'reason' => 'Govardhan/Go-krida prohibition proxy: moonset after sunset with illumination ≥ 2% and elongation ≥ 12° (not sunrise Pratipadā; altitude unavailable).',
            'tier' => 'T0',
        ];
    }

    // Lag is only a hard reject near zero (physical simultaneous moonset).
    if ($lagMinutes < CHANDRA_MIN_LAG_MINUTES_HARD_REJECT) {
        return [
            'status_code' => 'REJECTED_LAG_BELOW_HARD_MINIMUM',
            'allowed' => false,
            'selection_mode' => null,
            'computed_value' => $proxyPayload([
                'min_lag_hard_reject_minutes' => CHANDRA_MIN_LAG_MINUTES_HARD_REJECT,
            ]),
            'reason' => sprintf(
                'Moonset lag %.1f min is below the hard engineering floor of %.0f min (near-simultaneous moonset).',
                $lagMinutes,
                CHANDRA_MIN_LAG_MINUTES_HARD_REJECT,
            ),
            'tier' => 'T0',
        ];
    }

    // Source-attested field: monthly Chandra Darśana is Pratipadā/Dvitīyā only.
    $sourceAttestedTithi = in_array($tithiAtSunrise, CHANDRA_SOURCE_ATTESTED_TITHIS, true)
        || in_array($tithiAtSunset, CHANDRA_SOURCE_ATTESTED_TITHIS, true)
        || in_array($tithiAtBestTime, CHANDRA_SOURCE_ATTESTED_TITHIS, true);
    if (!$sourceAttestedTithi) {
        return [
            'status_code' => 'REJECTED_OUTSIDE_PRATIPADA_DVITIYA_FIELD',
            'allowed' => false,
            'selection_mode' => null,
            'computed_value' => $proxyPayload([
                'source_attested_tithis' => CHANDRA_SOURCE_ATTESTED_TITHIS,
            ]),
            'reason' => 'Outside source-attested Shukla Pratipadā/Dvitīyā candidate field for monthly Chandra Darśana.',
            'tier' => 'T1',
        ];
    }

    // Production-style horizon: moonrise before sunset and moonset after sunset when rise is known.
    $hasHorizonWindow = $hasPositiveLag
        && ($moonrise === null || ($moonrise < $sunset && $sunset < $moonset));
    if (!$hasHorizonWindow) {
        return [
            'status_code' => 'REJECTED_NO_POST_SUNSET_HORIZON_WINDOW',
            'allowed' => false,
            'selection_mode' => null,
            'computed_value' => $proxyPayload([
                'moonrise_jd' => $moonrise,
                'requires_moonrise_before_sunset_when_known' => true,
            ]),
            'reason' => 'No post-sunset horizon window (need moonset after sunset; when moonrise is known it must precede sunset).',
            'tier' => 'T0',
        ];
    }

    $twelveBhagaPassed = $isWaxing && $waxingSep >= CHANDRA_SURYA_SIDDHANTA_12_BHAGA_PROXY_DEG;

    // SS 10.2–10.4 inspired continuous lag proxy (always computed; date gate opt-in only).
    $ss10 = $computeSs10AtSunset($sunset);
    $ss10Asu = (float) ($ss10['ss10_lagnantara_continuous_proxy_asu']
        ?? $ss10['final_lagnantara_asu']
        ?? 0.0);
    $ss10Positive = (bool) ($ss10['ss10_lag_positive'] ?? ($ss10Asu > 0.0));
    $ss10Computed = (bool) ($ss10['ss10_lag_computed'] ?? true);
    // Engineering policy threshold only when --ss10-lag-gate; not a textual visibility law.
    $ss10PolicyThresholdMet = $ss10Asu > $ss10MinLagAsu;
    $ss10LagPassed = $ss10LagGate && $ss10PolicyThresholdMet;

    $ss10Diagnostic = [
        'ss10_lag_computed' => $ss10Computed,
        'ss10_lag_positive' => $ss10Positive,
        'ss10_lagnantara_continuous_proxy_asu' => $ss10Asu,
        'ss10_lag_minutes' => $ss10['ss10_lag_minutes'] ?? $ss10['final_lag_minutes'] ?? null,
        'ss10_visibility_decision' => $ss10LagGate
            ? ($ss10LagPassed ? 'ENGINEERING_POLICY_ACCEPT' : 'ENGINEERING_POLICY_REJECT')
            : null,
        'ss10_used_for_date' => $ss10LagPassed,
        'ss10_lag_gate_enabled' => $ss10LagGate,
        'ss10_min_lag_asu' => $ss10MinLagAsu,
        'ss10_policy_threshold_met' => $ss10PolicyThresholdMet,
        'interpretation' => 'classical_time_interval_proxy',
        'ss10_exact_lagnantara' => false,
        'ss10_continuous_astronomical_equivalent' => true,
        'status' => $ss10['status'] ?? null,
        'converged' => $ss10['converged'] ?? null,
        'iterations' => $ss10['iterations'] ?? null,
        'final_oa_diff_deg' => $ss10['final_oa_diff_deg'] ?? null,
        'lambda_sun_at_sunset_deg' => $ss10['lambda_sun_at_sunset_deg'] ?? null,
        'lambda_moon_at_sunset_deg' => $ss10['lambda_moon_at_sunset_deg'] ?? null,
        'claims' => $ss10['claims'] ?? null,
        // Legacy aliases
        'ss10_lagnantara_asu' => $ss10Asu,
        'final_lagnantara_asu' => $ss10Asu,
    ];

    $scriptureDiagnostics = [
        'surya_siddhanta_12_bhaga_proxy_deg' => CHANDRA_SURYA_SIDDHANTA_12_BHAGA_PROXY_DEG,
        'surya_siddhanta_12_bhaga_proxy_passed' => $twelveBhagaPassed,
        'waxing_separation_deg' => round($waxingSep, 4),
        'has_post_sunset_horizon_window' => $hasHorizonWindow,
        'dvitiya_daylight_muhurtas' => round($dvitiyaDaylightMuhurtas, 2),
        'dvitiya_active_in_aparahna' => $dvitiyaActiveInAparahna,
        'meets_six_muhurta_rule' => $meetsSixMuhurtaRule,
        'classical_sambhavya' => $classicalSambhavya,
        'modern_proxy_diagnostic_only' => !$legacyModernProxyDate,
        'best_time_jd' => $bestTimeJd,
        'ss10' => $ss10Diagnostic,
        // Flat aliases for console / older consumers
        'ss10_lag_computed' => $ss10Computed,
        'ss10_lag_positive' => $ss10Positive,
        'ss10_lagnantara_continuous_proxy_asu' => $ss10Asu,
        'ss10_visibility_decision' => $ss10Diagnostic['ss10_visibility_decision'],
        'ss10_used_for_date' => $ss10LagPassed,
    ];

    // --- Opt-in only: SS10 continuous lag proxy as engineering date gate ---
    if ($ss10LagPassed) {
        return [
            'status_code' => 'SUCCESS_SS10_LAG_PROXY_ENGINEERING_POLICY',
            'allowed' => true,
            'selection_mode' => 'SS10_LAG_PROXY_POLICY',
            'priority' => 1,
            'computed_value' => $proxyPayload($scriptureDiagnostics),
            'reason' => sprintf(
                'Opt-in engineering policy (--ss10-lag-gate): continuous SS10 lag proxy %.2f asu (%.1f min) > min %.2f. Not exact SS lagnāntara tables; not a textual visibility boolean; JME RA/Dec + modern spherical AD only.',
                $ss10Asu,
                (float) ($ss10Diagnostic['ss10_lag_minutes'] ?? 0.0),
                $ss10MinLagAsu,
            ),
            'tier' => 'T1',
        ];
    }

    // --- 1) Default primary: Sūrya Siddhānta 12-bhāga modern proxy ---
    if ($twelveBhagaPassed) {
        return [
            'status_code' => 'SUCCESS_SURYA_SIDDHANTA_12_BHAGA_PROXY',
            'allowed' => true,
            'selection_mode' => 'SCRIPTURE_PRIMARY',
            'priority' => 2,
            'computed_value' => $proxyPayload($scriptureDiagnostics),
            'reason' => 'Date path: waxing ecliptic separation ≥ 12° at sunset — modern Reading-A-style proxy for disputed SS 10.1 twelve-bhāga. SS10 lag is diagnostic unless --ss10-lag-gate.',
            'tier' => 'T1',
        ];
    }

    // --- 2) Classical nibandha sāmbhavya ---
    if ($classicalSambhavya) {
        return [
            'status_code' => 'SUCCESS_SHASTRIC_PROXY_SAMBHAVYA',
            'allowed' => true,
            'selection_mode' => 'PANCHANGA_FALLBACK',
            'priority' => 3,
            'computed_value' => $proxyPayload(array_merge($scriptureDiagnostics, [
                'proxy_below_acceptance_threshold' => $visibilityProxyScore <= CHANDRA_PROXY_ACCEPTANCE_THRESHOLD,
                'note' => 'Nibandha possibility indication; outranked by 12-bhāga (and opt-in SS10 policy) when present',
            ])),
            'reason' => 'Classical Dvitīyā sāmbhavya: ≥ 6 daylight muhūrtas AND Aparāhṇa overlap (secondary date path).',
            'tier' => 'T1',
        ];
    }

    // --- 3) Modern proxy date path (opt-in legacy only) ---
    if ($legacyModernProxyDate) {
        if ($visibilityProxyScore > 0.216) {
            return [
                'status_code' => 'SUCCESS_PROXY_HIGH_CONFIDENCE',
                'allowed' => true,
                'selection_mode' => 'ASTRONOMY',
                'priority' => 4,
                'computed_value' => $proxyPayload($scriptureDiagnostics),
                'reason' => 'Legacy modern high proxy score sets date (--legacy-modern-proxy-date).',
                'tier' => 'T3',
            ];
        }
        if ($visibilityProxyScore > -0.014) {
            return [
                'status_code' => 'SUCCESS_PROXY_MEDIUM_CONFIDENCE',
                'allowed' => true,
                'selection_mode' => 'ASTRONOMY',
                'priority' => 4,
                'computed_value' => $proxyPayload($scriptureDiagnostics),
                'reason' => 'Legacy modern medium proxy score sets date (--legacy-modern-proxy-date).',
                'tier' => 'T3',
            ];
        }
        if ($visibilityProxyScore > CHANDRA_PROXY_ACCEPTANCE_THRESHOLD && $allowLowConfidenceVisibility) {
            return [
                'status_code' => 'SUCCESS_PROXY_LOW_CONFIDENCE_VISIBILITY',
                'allowed' => true,
                'selection_mode' => 'ASTRONOMY',
                'priority' => 4,
                'computed_value' => $proxyPayload($scriptureDiagnostics),
                'reason' => 'Legacy low/borderline modern proxy accepted (flags enabled).',
                'tier' => 'T4',
            ];
        }
    }

    return [
        'status_code' => 'REJECTED_NO_SCRIPTURE_OR_CLASSICAL_PATH',
        'allowed' => false,
        'selection_mode' => null,
        'priority' => 99,
        'computed_value' => $proxyPayload($scriptureDiagnostics),
        'reason' => sprintf(
            'Below 12-bhāga proxy (%.2f° < %.1f°); classical sāmbhavya not met; modern score %.4f diagnostic only; SS10 continuous lag proxy %.2f asu (positive=%s, date_gate=%s)%s.',
            $waxingSep,
            CHANDRA_SURYA_SIDDHANTA_12_BHAGA_PROXY_DEG,
            $visibilityProxyScore,
            $ss10Asu,
            $ss10Positive ? 'yes' : 'no',
            $ss10LagGate ? 'on' : 'diagnostic_only',
            $legacyModernProxyDate ? ' and failed legacy modern gates' : '',
        ),
        'tier' => 'T1',
    ];
};

$buildCandidates = static function (array $season) use ($rangeEnd, $options, $dayBundle, $dvitiyaIntervalFor): array {
    $anchor = CarbonImmutable::parse((string) $season['anchor_date'], (string) $options['tz']);
    $candidates = [];

    for ($i = 0; $i < CHANDRA_MAX_POST_AMAVASYA_EVENINGS; $i++) {
        $date = $anchor->addDays($i);
        if ($date->greaterThan($rangeEnd)) {
            break;
        }

        $day = $dayBundle($date);
        if ((float) $day['sunset_jd'] + 1e-9 < (float) $season['amavasya_end_jd']) {
            continue;
        }

        $dvitiya = $dvitiyaIntervalFor($date, $day);
        $lagMinutes = $day['moonset_jd'] === null
            ? null
            : (((float) $day['moonset_jd'] - (float) $day['sunset_jd']) * 1440.0);

        $candidates[] = [
            'date_str' => (string) $day['date'],
            'sunrise_jd' => (float) $day['sunrise_jd'],
            'sunset_jd' => (float) $day['sunset_jd'],
            'next_sunrise_jd' => (float) $day['next_sunrise_jd'],
            'moonrise_jd' => $day['moonrise_jd'],
            'moonset_jd' => $day['moonset_jd'],
            'amavasya_end_jd' => (float) $season['amavasya_end_jd'],
            'dvitiya_start_jd' => (float) $dvitiya['start_jd'],
            'dvitiya_end_jd' => (float) $dvitiya['end_jd'],
            'waxing_separation_deg' => (float) $day['waxing_separation_deg'],
            // Snapshot field is geocentric sun–moon elongation at sunset, not Yallop topocentric ARCL.
            'geocentric_sun_moon_separation_deg' => (float) $day['waxing_separation_deg'],
            'geocentric_elongation_deg' => (float) $day['waxing_separation_deg'],
            'topocentric_arcv_deg' => null,
            'relative_azimuth_deg' => null,
            'moon_parallax_arcmin' => null,
            'moon_alt_deg' => null,
            'illumination_percent' => (float) $day['illumination_percent'],
            'lag_minutes' => $lagMinutes,
            'tithi_index_abs_at_sunrise' => (int) $day['tithi_index_abs'],
            'tithi_start_jd' => (float) $day['tithi_start_jd'],
            'tithi_end_jd' => (float) $day['tithi_end_jd'],
            'claims_exact_yallop_nao_69' => false,
            'visibility_proxy_basis' => 'snapshot_sunset_elongation_and_moonset_lag_illumination_hard_gate_only',
        ];
    }

    return $candidates;
};

/**
 * Scripture-first season selection:
 *   1) earliest SCRIPTURE_PRIMARY (12-bhāga)
 *   2) else earliest classical sāmbhavya
 *   3) else earliest legacy modern proxy (opt-in)
 * Modern scores remain on every candidate as diagnostics.
 */
$resolveMonthlyChandraDarshana = static function (array $candidates) use (
    $evaluateSingleCandidate,
    $options,
    $isScripturePrimarySuccess,
    $isClassicalSuccess,
    $isModernProxyDateSuccess,
): array {
    if ($candidates === []) {
        return [
            'status' => 'FAILED',
            'canonical_date' => null,
            'selected_candidate' => null,
            'rejected_candidates' => [],
            'selection_mode' => null,
            'first_proxy_astronomy_supported_date' => null,
            'classical_proxy_supported_date' => null,
            'decision_explanation' => null,
            'reason_code' => 'REJECTED_EMPTY_CANDIDATE_SET',
            'reason' => 'Candidate list provided to resolver is empty.',
        ];
    }

    $rejected = [];
    $allowed = [];

    foreach ($candidates as $candidate) {
        $eval = $evaluateSingleCandidate(
            $candidate,
            (string) $options['profile'],
            (bool) $options['allow_low_confidence_visibility'],
            (bool) $options['strict_shastric_veto'],
            (bool) $options['legacy_modern_proxy_date'],
            (bool) $options['ss10_lag_gate'],
            (float) $options['ss10_min_lag_asu'],
        );

        $record = [
            'date' => (string) ($candidate['date_str'] ?? 'UNKNOWN'),
            'status_code' => (string) $eval['status_code'],
            'reason' => (string) $eval['reason'],
            'tier' => (string) $eval['tier'],
            'selection_mode' => $eval['selection_mode'] ?? null,
            'priority' => (int) ($eval['priority'] ?? 99),
            'computed_value' => $eval['computed_value'],
            'candidate' => $candidate,
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
            'selected_candidate' => null,
            'rejected_candidates' => $rejected,
            'selection_mode' => null,
            'first_proxy_astronomy_supported_date' => null,
            'classical_proxy_supported_date' => null,
            'decision_explanation' => [
                'date_rule' => '12_bhaga_then_classical_then_optional_legacy_modern_ss10_diagnostic_by_default',
                'ss10_lag' => 'SS 10.2–10.4 inspired continuous lag proxy (diagnostic unless --ss10-lag-gate)',
                'scripture_primary' => 'Sūrya Siddhānta 12-bhāga modern proxy (≥12°) + horizon window',
                'classical' => 'Dvitīyā ≥6 daylight muhūrtas AND Aparāhṇa (secondary)',
                'modern' => 'visibility_proxy_score diagnostic only unless --legacy-modern-proxy-date',
                'fallback_used' => false,
            ],
            'reason_code' => 'REJECTED_NO_CANDIDATE_QUALIFIED',
            'reason' => 'No evening passed 12-bhāga proxy, classical sāmbhavya, or enabled opt-in date paths.',
        ];
    }

    // Priority then chronological date (scripture outranks earlier classical-only).
    usort($allowed, static function (array $a, array $b): int {
        $pa = (int) ($a['priority'] ?? 99);
        $pb = (int) ($b['priority'] ?? 99);
        if ($pa !== $pb) {
            return $pa <=> $pb;
        }

        return strcmp((string) $a['date'], (string) $b['date']);
    });
    $selected = $allowed[0];

    $scriptureDates = [];
    $classicalDates = [];
    $modernDates = [];
    foreach ($allowed as $hit) {
        $code = (string) $hit['status_code'];
        if ($isScripturePrimarySuccess($code)
            || in_array($hit['selection_mode'] ?? null, [
                'SCRIPTURE_PRIMARY',
                'SCRIPTURE_SS10_LAG',
                'SS10_LAG_PROXY_POLICY',
            ], true)) {
            $scriptureDates[] = (string) $hit['date'];
        }
        if ($isClassicalSuccess($code) || ($hit['selection_mode'] ?? null) === 'PANCHANGA_FALLBACK') {
            $classicalDates[] = (string) $hit['date'];
        }
        if ($isModernProxyDateSuccess($code) || ($hit['selection_mode'] ?? null) === 'ASTRONOMY') {
            $modernDates[] = (string) $hit['date'];
        }
    }
    sort($scriptureDates);
    sort($classicalDates);
    sort($modernDates);

    $notChosen = [];
    foreach (array_slice($allowed, 1) as $hit) {
        $notChosen[] = [
            'date' => $hit['date'],
            'status_code' => $hit['status_code'],
            'reason' => 'Allowed but outranked (scripture-primary preferred over classical/modern; then earliest date within same priority).',
            'tier' => $hit['tier'],
            'selection_mode' => $hit['selection_mode'],
            'priority' => $hit['priority'] ?? null,
            'computed_value' => $hit['computed_value'],
        ];
    }

    $canonicalDate = (string) $selected['date'];
    $fallbackUsed = ($selected['selection_mode'] ?? null) === 'PANCHANGA_FALLBACK'
        || $isClassicalSuccess((string) $selected['status_code']);

    return [
        'status' => 'RESOLVED',
        'canonical_date' => $canonicalDate,
        'first_scripture_primary_date' => $scriptureDates[0] ?? null,
        'first_proxy_astronomy_supported_date' => $modernDates[0] ?? $scriptureDates[0] ?? null,
        'classical_proxy_supported_date' => $classicalDates[0] ?? null,
        'decision_explanation' => [
            'date_rule' => 'priority_then_earliest_date',
            'ss10_lag' => 'continuous lag proxy always computed; sets date only with --ss10-lag-gate (engineering policy)',
            'scripture_primary' => 'Sūrya Siddhānta 12-bhāga modern proxy ≥12° (default primary date path)',
            'classical' => 'Dvitīyā 6 muhūrtas AND Aparāhṇa sāmbhavya (secondary)',
            'modern' => 'visibility_proxy_score diagnostic; date only with --legacy-modern-proxy-date',
            'fallback_used' => $fallbackUsed,
            'selected_mode' => $selected['selection_mode'] ?? null,
            'selected_priority' => $selected['priority'] ?? null,
        ],
        'selected_candidate' => [
            'date' => $canonicalDate,
            'status_code' => (string) $selected['status_code'],
            'reason' => (string) $selected['reason'],
            'computed_value' => $selected['computed_value'],
            'tier' => (string) $selected['tier'],
            'selection_mode' => $selected['selection_mode'],
            'priority' => $selected['priority'] ?? null,
            'candidate' => $selected['candidate'],
        ],
        'rejected_candidates' => array_merge($rejected, $notChosen),
        'selection_mode' => $selected['selection_mode'],
        'reason_code' => (string) $selected['status_code'],
        'reason' => (string) $selected['reason'],
    ];
};

$seasons = $discoverPostAmavasyaSeasons();
$rows = [];
$dates = [];

foreach ($seasons as $season) {
    $candidates = $buildCandidates($season);
    $resolved = $resolveMonthlyChandraDarshana($candidates);
    if (($resolved['canonical_date'] ?? null) !== null) {
        $dates[] = (string) $resolved['canonical_date'];
    }

    $rows[] = [
        'amavasya_anchor_date' => $season['anchor_date'],
        'amavasya_end_jd' => $season['amavasya_end_jd'],
        'canonical_date' => $resolved['canonical_date'],
        'first_scripture_primary_date' => $resolved['first_scripture_primary_date'] ?? null,
        'first_proxy_astronomy_supported_date' => $resolved['first_proxy_astronomy_supported_date'] ?? null,
        'classical_proxy_supported_date' => $resolved['classical_proxy_supported_date'] ?? null,
        'decision_explanation' => $resolved['decision_explanation'] ?? null,
        'status' => $resolved['status'],
        'selection_mode' => $resolved['selection_mode'] ?? null,
        'reason_code' => $resolved['reason_code'],
        'selected_candidate' => $resolved['selected_candidate'],
        'rejected_candidates' => $resolved['rejected_candidates'],
        'candidate_count' => count($candidates),
    ];
}

$dates = array_values(array_unique($dates));
sort($dates);

$payload = [
    'generated_at' => CarbonImmutable::now((string) $options['tz'])->toIso8601String(),
    'range' => [
        'from' => $rangeStart->toDateString(),
        'to' => $rangeEnd->toDateString(),
    ],
    'observer' => [
        'latitude' => (float) $options['lat'],
        'longitude' => (float) $options['lon'],
        'elevation' => (float) $options['elevation'],
        'timezone' => (string) $options['tz'],
    ],
    'profile' => (string) $options['profile'],
    'calendar_type' => (string) $options['calendar_type'],
    'implementation_scope' => [
        'algorithm_frozen' => false,
        'priority_policy' => '12_bhaga_then_classical_then_legacy_modern_ss10_diagnostic_by_default',
        'canonical_description' => 'Monthly Chandra Darśana: default date from 12-bhāga proxy + classical sāmbhavya; SS 10.2–10.4 inspired continuous lag is JME diagnostic (opt-in date gate only).',
        'public_ss10_description' => 'Surya_Siddhanta_10_2_10_4_inspired_iterative_lunar_setting_lag_model_using_JME_astronomical_inputs',
        'production_code_replaced' => false,
        'aligns_with_production_12_bhaga_gate' => true,
        'claims_exact_yallop_nao_69' => false,
        'claims_full_surya_siddhanta_chapter_10' => false,
        'claims_full_surya_siddhanta_recomputation' => false,
        'visibility_model' => '12_bhaga_plus_classical_sambhavya_plus_ss10_continuous_lag_proxy_diagnostic',
        'surya_siddhanta_basis' => [
            'SS_10_1', 'SS_10_2', 'SS_10_3', 'SS_10_4', 'SS_10_5',
            'SS_2_57', 'SS_7_9', 'SS_7_11',
        ],
        'twelve_bhaga_interpretation' => 'textually_disputed_between_angular_arc_and_ascensional_time',
        'angular_separation_reading_supported' => true,
        'ascensional_time_reading_supported' => true,
        'modern_proxy' => 'directed_moon_sun_ecliptic_longitude_separation_at_local_sunset',
        'modern_proxy_threshold_degrees' => CHANDRA_SURYA_SIDDHANTA_12_BHAGA_PROXY_DEG,
        'surya_siddhanta_12_bhaga_proxy_deg' => CHANDRA_SURYA_SIDDHANTA_12_BHAGA_PROXY_DEG,
        'ss10_lag_gate_enabled' => (bool) $options['ss10_lag_gate'],
        'ss10_min_lag_asu' => (float) $options['ss10_min_lag_asu'],
        'ss10_default_role' => 'diagnostic_classical_time_interval_proxy',
        'ss10_oa_method' => 'jme_equatorial_ra_dec_plus_modern_spherical_ad',
        'ss10_iteration' => 'SS_10_2_10_4_inspired_sthiribhuta_requery_jme_each_step',
        'ss10_engine' => 'JmeEphFFI',
        'ss10_exact_lagnantara' => false,
        'ss10_continuous_astronomical_equivalent' => true,
        'classical_computed_output' => 'iterated_local_setting_or_rising_interval_proxy_in_asu_prana',
        'classical_visibility_threshold_in_asu' => null,
        'fabricated_final_asu_720_gate' => false,
        'exact_drikkarma_implemented' => false,
        'exact_ancient_rasimana_cara_tables' => false,
        'positions_from_jme_calc_ut' => true,
        'equatorial_from_jme_calc_equatorial' => true,
        'speeds_from_jme_calc_speed' => true,
        'cara_ad_thin_wrapper_only' => true,
        'cara_ad_is_modern_spherical_equivalent_not_ss_table' => true,
        'source_attested_tithis' => CHANDRA_SOURCE_ATTESTED_TITHIS,
        'visibility_proxy_score' => 'weighted_0.75_elongation_0.25_lag_diagnostic_only_unless_legacy_modern_proxy_date',
        'legacy_modern_proxy_date' => (bool) $options['legacy_modern_proxy_date'],
        'proxy_band_labels' => [
            'PROXY_HIGH_CONFIDENCE',
            'PROXY_MEDIUM_CONFIDENCE',
            'PROXY_LOW_CONFIDENCE',
            'PROXY_BORDERLINE',
            'PROXY_REJECTED',
        ],
        'selection_policy' => 'scripture_primary_then_classical_then_legacy_modern_earliest_within_priority',
        'classical_sambhavya_rule' => 'dvitiya_ge_6_daylight_muhurtas_AND_aparahna_overlap',
        'min_lag_minutes_hard_reject' => CHANDRA_MIN_LAG_MINUTES_HARD_REJECT,
        'proxy_acceptance_threshold' => CHANDRA_PROXY_ACCEPTANCE_THRESHOLD,
        'min_illumination_percent' => CHANDRA_MIN_ILLUMINATION_PERCENT,
        'govardhan_night_moon_min_illumination_percent' => CHANDRA_NIGHT_MOON_MIN_ILLUMINATION_PERCENT,
        'govardhan_night_moon_min_elongation_deg' => CHANDRA_NIGHT_MOON_MIN_ELONGATION_DEG,
        'accepted_scope' => [
            'monthly_chandra_darshana',
            'bhuj_class_locations',
            'amanta_calendar',
            'hindu_festival_engine',
        ],
        'exact_yallop_missing_inputs' => [
            'topocentric_ARCV_at_best_time',
            'topocentric_ARCL_at_best_time',
            'relative_azimuth_DAZ_at_best_time',
            'topocentric_moon_altitude_at_best_time',
        ],
    ],
    'counts' => [
        'post_amavasya_seasons' => count($rows),
        'resolved_dates' => count($dates),
        'gregorian_months_in_range' => (($rangeEnd->year - $rangeStart->year) * 12) + ($rangeEnd->month - $rangeStart->month) + 1,
    ],
    'dates' => $dates,
    'rows' => $rows,
];

$outputPath = $options['output'];
if ($outputPath === null || $outputPath === '') {
    $outputDir = $baseDir . '/scripts/output/experimental';
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0777, true);
    }
    $outputPath = sprintf(
        '%s/chandra_lossless_%s_to_%s.json',
        $outputDir,
        str_replace('-', '_', $rangeStart->toDateString()),
        str_replace('-', '_', $rangeEnd->toDateString()),
    );
}

panchang_script_write_json((string) $outputPath, $payload);

echo "Written:\n{$outputPath}\n\n";
printf(
    "Range %s to %s (%d Gregorian months) produced %d post-Amavasya seasons and %d resolved Chandra Darshana dates.\n\n",
    $rangeStart->toDateString(),
    $rangeEnd->toDateString(),
    (int) $payload['counts']['gregorian_months_in_range'],
    count($rows),
    count($dates),
);

foreach ($rows as $index => $row) {
    $selected = (array) ($row['selected_candidate'] ?? []);
    $computed = (array) ($selected['computed_value'] ?? []);
    $candidate = (array) ($selected['candidate'] ?? []);
    printf(
        "%02d. Amavasya~%s -> %s | %s | mode=%s score=%s band=%s lag=%s min elong=%s deg age=%sh tithi_abs=%s | astro_proxy=%s classical=%s\n",
        $index + 1,
        (string) $row['amavasya_anchor_date'],
        (string) ($row['canonical_date'] ?? 'UNRESOLVED'),
        (string) $row['reason_code'],
        (string) ($row['selection_mode'] ?? ($selected['selection_mode'] ?? 'n/a')),
        isset($computed['visibility_proxy_score']) ? sprintf('%.4f', (float) $computed['visibility_proxy_score']) : 'n/a',
        (string) ($computed['proxy_band'] ?? 'n/a'),
        isset($candidate['lag_minutes']) && $candidate['lag_minutes'] !== null
            ? sprintf('%.1f', (float) $candidate['lag_minutes'])
            : 'n/a',
        isset($candidate['waxing_separation_deg']) ? sprintf('%.2f', (float) $candidate['waxing_separation_deg']) : 'n/a',
        isset($computed['lunar_age_hours']) ? sprintf('%.1f', (float) $computed['lunar_age_hours']) : 'n/a',
        isset($candidate['tithi_index_abs_at_sunrise']) ? (string) $candidate['tithi_index_abs_at_sunrise'] : 'n/a',
        (string) ($row['first_proxy_astronomy_supported_date'] ?? 'n/a'),
        (string) ($row['classical_proxy_supported_date'] ?? 'n/a'),
    );
}
