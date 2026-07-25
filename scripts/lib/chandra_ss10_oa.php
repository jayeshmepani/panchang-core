<?php

declare(strict_types=1);

/**
 * Sūrya Siddhānta 10.2–10.4 *inspired* continuous lag model (JmeEph-first).
 *
 * From JME:
 *   - ecliptic longitude / latitude / speed  (jme_calc_ut + SIDEREAL + SPEED)
 *   - equatorial RA / Dec / speeds           (jme_calc_ut + EQUATORIAL + SPEED)
 *
 * Thin non-JME glue only (JME has no rāśimāna/cara tables):
 *   - ascensional difference AD: sin(AD)=tan(φ)·tan(δ)
 *     = modern spherical *astronomical equivalent* of cara, not “SS cara tables”
 *   - OD = α + AD; lag proxy asu = ΔOD × 60
 *   - SS 10.3–10.4 style re-query iteration (sthirībhūtā-inspired)
 *
 * Output is a continuous lag *proxy*, not exact classical lagnāntarāsavaḥ tables.
 * Not: dṛkkarma tables, mandatory ≥720 asu, full ch.10 recompute.
 */
use FFI\CData;
use JmeEph\FFI\JmeEphFFI;

/** Asu (prāṇa) per sidereal day. */
const CHANDRA_SS10_ASU_PER_DAY = 21600.0;

/** Asu per degree of earth rotation / OA. */
const CHANDRA_SS10_ASU_PER_DEGREE = 60.0;

/**
 * @return array{xx: CData, err: CData}
 */
function chandra_ss10_jme_buffers(JmeEphFFI $jme): array
{
    static $cache = [];
    $id = spl_object_id($jme);
    if (!isset($cache[$id])) {
        $ffi = $jme->getFFI();
        $cache[$id] = [
            'xx' => $ffi->new('double[6]'),
            'err' => $ffi->new('char[256]'),
        ];
    }

    return $cache[$id];
}

function chandra_ss10_normalize_deg(float $degrees): float
{
    $d = fmod($degrees, 360.0);

    return $d < 0.0 ? $d + 360.0 : $d;
}

function chandra_ss10_signed_delta_deg(float $a, float $b): float
{
    $d = chandra_ss10_normalize_deg($a - $b);
    if ($d > 180.0) {
        $d -= 360.0;
    }

    return $d;
}

function chandra_ss10_jme_base_flags(): int
{
    return JmeEphFFI::JME_CALC_HIGH_PRECISION | JmeEphFFI::JME_CALC_NO_ABERRATION;
}

/**
 * @return array{
 *   lon_deg: float,
 *   lat_deg: float,
 *   dist: float,
 *   lon_speed_deg_per_day: float,
 *   lat_speed_deg_per_day: float,
 *   ok: bool,
 *   error: string
 * }
 */
function chandra_ss10_jme_ecliptic_state(JmeEphFFI $jme, float $jd, int $body): array
{
    $buf = chandra_ss10_jme_buffers($jme);
    $flags = chandra_ss10_jme_base_flags()
        | JmeEphFFI::JME_CALC_SIDEREAL
        | JmeEphFFI::JME_CALC_SPEED;
    $rc = $jme->jme_calc_ut($jd, $body, $flags, $buf['xx'], $buf['err']);
    $ok = $rc === JmeEphFFI::JME_OK && is_finite((float) $buf['xx'][0]);

    return [
        'lon_deg' => $ok ? chandra_ss10_normalize_deg((float) $buf['xx'][0]) : 0.0,
        'lat_deg' => $ok ? (float) $buf['xx'][1] : 0.0,
        'dist' => $ok ? (float) $buf['xx'][2] : 0.0,
        'lon_speed_deg_per_day' => $ok ? (float) $buf['xx'][3] : 0.0,
        'lat_speed_deg_per_day' => $ok ? (float) $buf['xx'][4] : 0.0,
        'ok' => $ok,
        'error' => $ok ? '' : (string) FFI::string($buf['err']),
    ];
}

/**
 * @return array{
 *   ra_deg: float,
 *   dec_deg: float,
 *   dist: float,
 *   ra_speed_deg_per_day: float,
 *   dec_speed_deg_per_day: float,
 *   ok: bool,
 *   error: string
 * }
 */
function chandra_ss10_jme_equatorial_state(JmeEphFFI $jme, float $jd, int $body): array
{
    $buf = chandra_ss10_jme_buffers($jme);
    $flags = chandra_ss10_jme_base_flags()
        | JmeEphFFI::JME_CALC_EQUATORIAL
        | JmeEphFFI::JME_CALC_SPEED;
    $rc = $jme->jme_calc_ut($jd, $body, $flags, $buf['xx'], $buf['err']);
    $ok = $rc === JmeEphFFI::JME_OK && is_finite((float) $buf['xx'][0]);

    return [
        'ra_deg' => $ok ? chandra_ss10_normalize_deg((float) $buf['xx'][0]) : 0.0,
        'dec_deg' => $ok ? (float) $buf['xx'][1] : 0.0,
        'dist' => $ok ? (float) $buf['xx'][2] : 0.0,
        'ra_speed_deg_per_day' => $ok ? (float) $buf['xx'][3] : 0.0,
        'dec_speed_deg_per_day' => $ok ? (float) $buf['xx'][4] : 0.0,
        'ok' => $ok,
        'error' => $ok ? '' : (string) FFI::string($buf['err']),
    ];
}

/**
 * Modern spherical ascensional difference (astronomical equivalent of *cara*).
 * Not a Siddhāntic rāśimāna/cara-khaṇḍa table implementation.
 */
function chandra_ss10_ascensional_difference_deg(float $deltaDeg, float $phiDeg): float
{
    $phi = deg2rad($phiDeg);
    $delta = deg2rad($deltaDeg);
    if (abs(cos($phi)) < 1e-12 || abs(cos($delta)) < 1e-12) {
        return 0.0;
    }

    $x = tan($phi) * tan($delta);
    if ($x > 1.0) {
        $x = 1.0;
    } elseif ($x < -1.0) {
        $x = -1.0;
    }

    return rad2deg(asin($x));
}

/**
 * @param array{ra_deg: float, dec_deg: float} $equ
 *
 * @return array{od_deg: float, alpha_deg: float, delta_deg: float, ad_deg: float, method: string}
 */
function chandra_ss10_od_from_jme_equatorial(array $equ, float $phiDeg): array
{
    $ad = chandra_ss10_ascensional_difference_deg((float) $equ['dec_deg'], $phiDeg);
    $od = chandra_ss10_normalize_deg((float) $equ['ra_deg'] + $ad);

    return [
        'od_deg' => $od,
        'alpha_deg' => (float) $equ['ra_deg'],
        'delta_deg' => (float) $equ['dec_deg'],
        'ad_deg' => $ad,
        'method' => 'jme_equatorial_plus_modern_spherical_ad_setting',
        'ad_interpretation' => 'astronomical_equivalent_of_cara_not_ss_table_cara',
    ];
}

/**
 * Continuous lag proxy at one JD (not exact SS table lagnāntarāsavaḥ).
 *
 * @return array<string, mixed>
 */
function chandra_ss10_lagnantara_proxy_asu_jme(JmeEphFFI $jme, float $jd, float $phiDeg): array
{
    $sunEcl = chandra_ss10_jme_ecliptic_state($jme, $jd, JmeEphFFI::JME_BODY_SUN);
    $moonEcl = chandra_ss10_jme_ecliptic_state($jme, $jd, JmeEphFFI::JME_BODY_MOON);
    $sunEqu = chandra_ss10_jme_equatorial_state($jme, $jd, JmeEphFFI::JME_BODY_SUN);
    $moonEqu = chandra_ss10_jme_equatorial_state($jme, $jd, JmeEphFFI::JME_BODY_MOON);
    $ok = $sunEcl['ok'] && $moonEcl['ok'] && $sunEqu['ok'] && $moonEqu['ok'];

    $odSun = chandra_ss10_od_from_jme_equatorial($sunEqu, $phiDeg);
    $odMoon = chandra_ss10_od_from_jme_equatorial($moonEqu, $phiDeg);
    $diffDeg = chandra_ss10_signed_delta_deg((float) $odMoon['od_deg'], (float) $odSun['od_deg']);
    $asu = $diffDeg * CHANDRA_SS10_ASU_PER_DEGREE;

    return [
        // Canonical proxy name
        'ss10_lagnantara_continuous_proxy_asu' => $asu,
        // Legacy alias (same value)
        'lagnantara_asu' => $asu,
        'oa_diff_deg' => $diffDeg,
        'od_sun' => $odSun,
        'od_moon' => $odMoon,
        'sun_equ' => $sunEqu,
        'moon_equ' => $moonEqu,
        'sun_ecl' => $sunEcl,
        'moon_ecl' => $moonEcl,
        'lambda_sun_desc_deg' => chandra_ss10_normalize_deg((float) $sunEcl['lon_deg'] + 180.0),
        'lambda_moon_desc_deg' => chandra_ss10_normalize_deg((float) $moonEcl['lon_deg'] + 180.0),
        'ok' => $ok,
        'interpretation' => 'classical_time_interval_proxy',
    ];
}

/**
 * @deprecated use chandra_ss10_lagnantara_proxy_asu_jme
 */
function chandra_ss10_lagnantara_asu_jme(JmeEphFFI $jme, float $jd, float $phiDeg): array
{
    return chandra_ss10_lagnantara_proxy_asu_jme($jme, $jd, $phiDeg);
}

/**
 * SS 10.2–10.4 inspired iteration; re-query JME each step.
 *
 * @return array<string, mixed>
 */
function chandra_ss10_iterate_chapter10_jme(
    JmeEphFFI $jme,
    float $sunsetJd,
    float $phiDeg,
    int $maxIter = 25,
    float $convergeAsu = 0.5,
): array {
    $asu = 0.0;
    $prevAsu = null;
    $iterations = [];
    $converged = false;
    $n = 0;
    $last = null;
    $jd = $sunsetJd;

    for ($n = 1; $n <= $maxIter; $n++) {
        $last = chandra_ss10_lagnantara_proxy_asu_jme($jme, $jd, $phiDeg);
        if (!$last['ok']) {
            return [
                'status' => 'JME_ERROR',
                'converged' => false,
                'iterations' => $n,
                'iteration_trace' => $iterations,
                'sunset_jd' => $sunsetJd,
                'phi_deg' => $phiDeg,
                'ss10_lagnantara_continuous_proxy_asu' => 0.0,
                'final_lagnantara_asu' => 0.0,
                'final_lag_minutes' => 0.0,
                'ss10_lag_positive' => false,
                'ss10_lag_computed' => false,
                'error' => 'jme_calc_ut failed for sun/moon state',
                'interpretation' => 'classical_time_interval_proxy',
                'claims' => chandra_ss10_claims_meta(),
            ];
        }

        $asu = (float) $last['ss10_lagnantara_continuous_proxy_asu'];
        $iterations[] = [
            'iter' => $n,
            'jd' => $jd,
            'lambda_sun_deg' => round((float) $last['sun_ecl']['lon_deg'], 6),
            'lambda_moon_deg' => round((float) $last['moon_ecl']['lon_deg'], 6),
            'ra_sun_deg' => round((float) $last['sun_equ']['ra_deg'], 6),
            'ra_moon_deg' => round((float) $last['moon_equ']['ra_deg'], 6),
            'dec_sun_deg' => round((float) $last['sun_equ']['dec_deg'], 6),
            'dec_moon_deg' => round((float) $last['moon_equ']['dec_deg'], 6),
            'ss10_lagnantara_continuous_proxy_asu' => round($asu, 4),
            'oa_diff_deg' => round((float) $last['oa_diff_deg'], 6),
            'sun_lon_speed' => round((float) $last['sun_ecl']['lon_speed_deg_per_day'], 6),
            'moon_lon_speed' => round((float) $last['moon_ecl']['lon_speed_deg_per_day'], 6),
        ];

        if ($prevAsu !== null && abs($asu - $prevAsu) <= $convergeAsu) {
            $converged = true;
            break;
        }

        $prevAsu = $asu;
        $jd = $sunsetJd + ($asu / CHANDRA_SS10_ASU_PER_DAY);
    }

    $lagMinutes = ($asu / CHANDRA_SS10_ASU_PER_DAY) * 1440.0;
    $positive = $asu > 0.0;

    return [
        'status' => $converged ? 'CONVERGED' : 'MAX_ITER',
        'converged' => $converged,
        'iterations' => $n,
        'iteration_trace' => $iterations,
        'sunset_jd' => $sunsetJd,
        'eval_jd_final' => $jd,
        'phi_deg' => $phiDeg,
        'lambda_sun_at_sunset_deg' => isset($iterations[0]) ? $iterations[0]['lambda_sun_deg'] : null,
        'lambda_moon_at_sunset_deg' => isset($iterations[0]) ? $iterations[0]['lambda_moon_deg'] : null,
        'daily_motion_sun_deg' => isset($iterations[0]) ? $iterations[0]['sun_lon_speed'] : null,
        'daily_motion_moon_deg' => isset($iterations[0]) ? $iterations[0]['moon_lon_speed'] : null,
        // Canonical proxy outputs
        'ss10_lag_computed' => true,
        'ss10_lagnantara_continuous_proxy_asu' => round($asu, 4),
        'ss10_lag_minutes' => round($lagMinutes, 3),
        'ss10_lag_positive' => $positive,
        // SS computes an interval; visibility/date decision is a separate policy layer.
        'ss10_visibility_decision' => null,
        'interpretation' => 'classical_time_interval_proxy',
        // Legacy aliases (same numbers)
        'final_lagnantara_asu' => round($asu, 4),
        'final_lag_minutes' => round($lagMinutes, 3),
        'final_oa_diff_deg' => round((float) ($last['oa_diff_deg'] ?? 0.0), 6),
        'final_od_sun' => $last['od_sun'] ?? null,
        'final_od_moon' => $last['od_moon'] ?? null,
        'ss10_positive_lag' => $positive,
        'engine' => 'JmeEphFFI',
        'claims' => chandra_ss10_claims_meta(),
    ];
}

/**
 * @return array<string, bool|string>
 */
function chandra_ss10_claims_meta(): array
{
    return [
        'positions_from_jme_calc_ut' => true,
        'equatorial_from_jme_calc_equatorial' => true,
        'speeds_from_jme_calc_speed' => true,
        'ss10_exact_lagnantara' => false,
        'ss10_continuous_astronomical_equivalent' => true,
        'exact_ancient_rasimana_cara_tables' => false,
        'cara_ad_thin_wrapper_only' => true,
        'cara_ad_is_modern_spherical_equivalent_not_ss_table' => true,
        'exact_drikkarma_implemented' => false,
        'ss_10_2_10_4_iteration_structure' => true,
        'fabricated_final_asu_720_gate' => false,
        'claims_full_surya_siddhanta_recomputation' => false,
        'public_description' => 'Surya_Siddhanta_10_2_10_4_inspired_iterative_lunar_setting_lag_model_using_JME',
    ];
}

/**
 * @return array<string, mixed>
 */
function chandra_ss10_iterate_chapter10(
    float $sunsetJd,
    float $phiDeg,
    $sunLonAtJd = null,
    $moonLonAtJd = null,
    float $epsDeg = 23.4392911,
    int $maxIter = 25,
    float $convergeAsu = 0.5,
    ?JmeEphFFI $jme = null,
): array {
    if ($jme instanceof JmeEphFFI) {
        return chandra_ss10_iterate_chapter10_jme($jme, $sunsetJd, $phiDeg, $maxIter, $convergeAsu);
    }

    return [
        'status' => 'NO_JME',
        'converged' => false,
        'iterations' => 0,
        'ss10_lag_computed' => false,
        'ss10_lagnantara_continuous_proxy_asu' => 0.0,
        'final_lagnantara_asu' => 0.0,
        'final_lag_minutes' => 0.0,
        'ss10_lag_positive' => false,
        'ss10_visibility_decision' => null,
        'error' => 'JmeEphFFI instance required for SS10 path',
        'interpretation' => 'classical_time_interval_proxy',
        'claims' => chandra_ss10_claims_meta(),
    ];
}
