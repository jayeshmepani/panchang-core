<?php

declare(strict_types=1);

/**
 * Yallop (1997) q-criterion — HMNAO Technical Note 69 coordinate conventions.
 *
 * Published definitions (TN 69):
 *   - ARCL  = Earth-centred Sun–Moon angular separation (geocentric)
 *   - ARCV  = geocentric Moon altitude − geocentric Sun altitude (airless)
 *   - DAZ   = absolute geocentric azimuth difference
 *   - W′    = topocentric crescent width (arcminutes)
 *   - Tb    = Ts + (4/9)×(Tm − Ts)
 *   - q     = (ARCV − poly(W′)) / 10
 *
 * poly(W′) = 11.8371 − 6.3226 W′ + 0.7319 W′² − 0.1018 W′³  (W′ in arcmin)
 *
 * Topocentric correction is used only for lunar semi-diameter → W′
 * (TN 69 eqs. 3.8–3.10), not for ARCL/ARCV.
 *
 * Labels carefully:
 *   - SD = 0.27245 × π with π = arcsin(a/Δ) per TN 69 (3.8), not arcsin(k a/Δ)
 *   - SD′ and W′ follow TN 69 (3.9)–(3.10); ephemeris Δ from JME, not NAO tables
 *
 * Not used as date gates: fixed 10° elongation / 39 min lag.
 *
 * Reference: B.D. Yallop, HMNAO Technical Note 69 (1997).
 */
use FFI\CData;
use JmeEph\FFI\JmeEphFFI;

/** WGS84 semi-major axis (km) — for parallax / modern SD equivalent only. */
const CHANDRA_YALLOP_WGS84_A_KM = 6378.137;
const CHANDRA_YALLOP_WGS84_F = 1.0 / 298.257223563;
const CHANDRA_YALLOP_AU_KM = 149597870.7;
/** TN 69 eq. (3.8) mean Moon radius factor. */
const CHANDRA_YALLOP_MOON_RADIUS_FACTOR = 0.27245;
/** Operational Danjon ARCL guard (degrees); not part of q polynomial. */
const CHANDRA_YALLOP_DANJON_ARCL_DEG = 7.0;

/** Yallop Indian-method cubic (TN 69 / adopted form), W′ in arcminutes. */
const CHANDRA_YALLOP_POLY_C0 = 11.8371;
const CHANDRA_YALLOP_POLY_C1 = -6.3226;
const CHANDRA_YALLOP_POLY_C2 = 0.7319;
const CHANDRA_YALLOP_POLY_C3 = -0.1018;

/**
 * @return array{xx: CData, err: CData}
 */
function chandra_yallop_buffers(JmeEphFFI $jme): array
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

function chandra_yallop_base_flags(): int
{
    return JmeEphFFI::JME_CALC_HIGH_PRECISION;
}

function chandra_yallop_norm_deg(float $deg): float
{
    $d = fmod($deg, 360.0);
    if ($d < 0.0) {
        $d += 360.0;
    }

    return $d;
}

/**
 * Geocentric equatorial position in the mean equator/equinox of date.
 *
 * JME_CALC_NO_NUTATION is required because jme_sidereal_time() returns GMST.
 * This gives a coordinate-consistent mean RA/Dec + GMST pairing for altitude
 * and azimuth calculations. Other apparent-position corrections follow the
 * selected JME base flags.
 *
 * @return array{ra_deg: float, dec_deg: float, dist_au: float, ok: bool, error: string}
 */
function chandra_yallop_equatorial(JmeEphFFI $jme, float $jd, int $body): array
{
    $buf = chandra_yallop_buffers($jme);
    $flags = chandra_yallop_base_flags()
        | JmeEphFFI::JME_CALC_EQUATORIAL
        | JmeEphFFI::JME_CALC_NO_NUTATION;
    $rc = $jme->jme_calc_ut($jd, $body, $flags, $buf['xx'], $buf['err']);
    $ok = $rc === JmeEphFFI::JME_OK && is_finite((float) $buf['xx'][0]) && is_finite((float) $buf['xx'][2]);

    return [
        'ra_deg' => $ok ? chandra_yallop_norm_deg((float) $buf['xx'][0]) : 0.0,
        'dec_deg' => $ok ? (float) $buf['xx'][1] : 0.0,
        'dist_au' => $ok ? (float) $buf['xx'][2] : 0.0,
        'ok' => $ok,
        'error' => $ok ? '' : (string) FFI::string($buf['err']),
    ];
}

/**
 * Geocentric ecliptic longitude/latitude (degrees) — tropical apparent.
 *
 * @return array{lon_deg: float, lat_deg: float, dist_au: float, ok: bool, error: string}
 */
function chandra_yallop_ecliptic(JmeEphFFI $jme, float $jd, int $body): array
{
    $buf = chandra_yallop_buffers($jme);
    $flags = chandra_yallop_base_flags();
    $rc = $jme->jme_calc_ut($jd, $body, $flags, $buf['xx'], $buf['err']);
    $ok = $rc === JmeEphFFI::JME_OK && is_finite((float) $buf['xx'][0]);

    return [
        'lon_deg' => $ok ? chandra_yallop_norm_deg((float) $buf['xx'][0]) : 0.0,
        'lat_deg' => $ok ? (float) $buf['xx'][1] : 0.0,
        'dist_au' => $ok ? (float) $buf['xx'][2] : 0.0,
        'ok' => $ok,
        'error' => $ok ? '' : (string) FFI::string($buf['err']),
    ];
}

/**
 * Local mean sidereal time (degrees).
 *
 * jme_sidereal_time() returns Greenwich Mean Sidereal Time in hours.
 * Mean equatorial RA/Dec are requested with JME_CALC_NO_NUTATION, so this
 * function forms the matching local mean sidereal time:
 *
 *   LMST = GMST × 15° + east longitude.
 */
function chandra_yallop_lst_deg(JmeEphFFI $jme, float $jd, float $lonEastDeg): float
{
    $siderealHours = (float) $jme->jme_sidereal_time($jd);

    return chandra_yallop_norm_deg($siderealHours * 15.0 + $lonEastDeg);
}

/**
 * WGS84 ρ cos φ′, ρ sin φ′ (equatorial radii) — only for optional topo SD path / diagnostics.
 *
 * @return array{rho_cos_phi_p: float, rho_sin_phi_p: float}
 */
function chandra_yallop_wgs84_rho(float $latDeg, float $elevationM): array
{
    $a = CHANDRA_YALLOP_WGS84_A_KM;
    $f = CHANDRA_YALLOP_WGS84_F;
    $e2 = $f * (2.0 - $f);
    $phi = deg2rad($latDeg);
    $sinPhi = sin($phi);
    $cosPhi = cos($phi);
    $n = $a / sqrt(1.0 - $e2 * $sinPhi * $sinPhi);
    $hKm = $elevationM / 1000.0;

    return [
        'rho_cos_phi_p' => (($n + $hKm) / $a) * $cosPhi,
        'rho_sin_phi_p' => (($n * (1.0 - $e2) + $hKm) / $a) * $sinPhi,
    ];
}

/**
 * Optional topocentric Moon (diagnostics / alternate SD′). Not used for ARCL/ARCV.
 *
 * @return array{ra_deg: float, dec_deg: float, dist_km: float, parallax_horizontal_deg: float}
 */
function chandra_yallop_topocentric_moon(
    float $raDeg,
    float $decDeg,
    float $distAu,
    float $lstDeg,
    float $latDeg,
    float $elevationM,
): array {
    $distKm = max($distAu * CHANDRA_YALLOP_AU_KM, 1.0);
    $a = CHANDRA_YALLOP_WGS84_A_KM;
    $sinPi = min(1.0, $a / $distKm);
    $pi = asin($sinPi);
    $rho = chandra_yallop_wgs84_rho($latDeg, $elevationM);
    $rhoCos = $rho['rho_cos_phi_p'];
    $rhoSin = $rho['rho_sin_phi_p'];

    $ha = deg2rad($lstDeg - $raDeg);
    $dec = deg2rad($decDeg);
    $cosDec = cos($dec);
    $sinDec = sin($dec);
    $cosHa = cos($ha);
    $sinHa = sin($ha);

    $deltaRa = atan2(
        -$rhoCos * $sinPi * $sinHa,
        $cosDec - $rhoCos * $sinPi * $cosHa,
    );
    $topDec = atan2(
        ($sinDec - $rhoSin * $sinPi) * cos($deltaRa),
        $cosDec - $rhoCos * $sinPi * $cosHa,
    );
    $topRa = deg2rad($raDeg) + $deltaRa;
    $cosZenithal = $rhoCos * $cosDec * $cosHa + $rhoSin * $sinDec;
    $rho2 = $rhoCos * $rhoCos + $rhoSin * $rhoSin;
    $topDistKm = $distKm * sqrt(max(0.0, 1.0 - 2.0 * $sinPi * $cosZenithal + $sinPi * $sinPi * $rho2));

    return [
        'ra_deg' => chandra_yallop_norm_deg(rad2deg($topRa)),
        'dec_deg' => rad2deg($topDec),
        'dist_km' => $topDistKm,
        'parallax_horizontal_deg' => rad2deg($pi),
    ];
}

/**
 * Geometric altitude / azimuth (airless). Azimuth north→east [0, 360).
 *
 * @return array{alt_deg: float, az_deg: float}
 */
function chandra_yallop_alt_az(float $raDeg, float $decDeg, float $lstDeg, float $latDeg): array
{
    $ha = deg2rad($lstDeg - $raDeg);
    $phi = deg2rad($latDeg);
    $dec = deg2rad($decDeg);

    $sinAlt = sin($phi) * sin($dec) + cos($phi) * cos($dec) * cos($ha);
    if ($sinAlt > 1.0) {
        $sinAlt = 1.0;
    } elseif ($sinAlt < -1.0) {
        $sinAlt = -1.0;
    }

    $alt = asin($sinAlt);

    $y = -cos($dec) * sin($ha);
    $x = sin($dec) * cos($phi) - cos($dec) * sin($phi) * cos($ha);
    $az = atan2($y, $x);
    if ($az < 0.0) {
        $az += 2.0 * M_PI;
    }

    return [
        'alt_deg' => rad2deg($alt),
        'az_deg' => rad2deg($az),
    ];
}

/**
 * Angular separation (degrees) via stable atan2(|a×b|, a·b).
 */
function chandra_yallop_angular_sep_deg(
    float $ra1,
    float $dec1,
    float $ra2,
    float $dec2,
): float {
    $d1 = deg2rad($dec1);
    $d2 = deg2rad($dec2);
    $a1 = deg2rad($ra1);
    $a2 = deg2rad($ra2);

    $x1 = cos($d1) * cos($a1);
    $y1 = cos($d1) * sin($a1);
    $z1 = sin($d1);
    $x2 = cos($d2) * cos($a2);
    $y2 = cos($d2) * sin($a2);
    $z2 = sin($d2);

    $dot = $x1 * $x2 + $y1 * $y2 + $z1 * $z2;
    $cx = $y1 * $z2 - $z1 * $y2;
    $cy = $z1 * $x2 - $x1 * $z2;
    $cz = $x1 * $y2 - $y1 * $x2;
    $cross = sqrt($cx * $cx + $cy * $cy + $cz * $cz);

    return rad2deg(atan2($cross, $dot));
}

/**
 * @return array<string, mixed>|null
 */
function chandra_yallop_pheno_moon(JmeEphFFI $jme, float $jd): ?array
{
    $ffi = $jme->getFFI();
    $attr = $ffi->new('double[20]');
    $err = $ffi->new('char[256]');
    $rc = $ffi->jme_pheno_ut($jd, JmeEphFFI::JME_BODY_MOON, chandra_yallop_base_flags(), $attr, $err);
    if ($rc !== JmeEphFFI::JME_OK && $rc !== 0) {
        return null;
    }

    return [
        'phase_angle_deg' => (float) $attr[0],
        'illuminated_fraction' => (float) $attr[1],
        'elongation_deg' => (float) $attr[2],
        'diameter_arcsec' => (float) $attr[3],
        'semi_diameter_arcmin' => ((float) $attr[3]) / 120.0,
        'apparent_magnitude' => (float) $attr[4],
    ];
}

/**
 * Geocentric lunar semi-diameter (arcmin) — Yallop TN 69 eq. (3.8):
 *   π = arcsin(a/Δ)   (horizontal parallax)
 *   SD = 0.27245 × π  (same angular unit)
 *
 * Not arcsin(0.27245 · a/Δ), which is a different modern geometric form.
 */
function chandra_yallop_sd_geocentric_arcmin(float $moonDistKm): float
{
    $sinPi = min(1.0, CHANDRA_YALLOP_WGS84_A_KM / max($moonDistKm, 1.0));
    $parallaxArcmin = rad2deg(asin($sinPi)) * 60.0;

    return CHANDRA_YALLOP_MOON_RADIUS_FACTOR * $parallaxArcmin;
}

/**
 * Yallop TN 69 width chain (eqs. 3.8–3.10):
 *   π  = arcsin(a/Δ)
 *   SD = 0.27245 × π
 *   SD′ = SD · (1 + sin h · sin π)   h = geocentric Moon altitude
 *   W′  = SD′ · (1 − cos ARCL)       ARCL geocentric; W′ arcminutes
 *
 * @return array{
 *   sd_geo_arcmin: float,
 *   sd_prime_arcmin: float,
 *   w_prime_arcmin: float,
 *   parallax_deg: float,
 *   parallax_rad: float,
 *   parallax_arcmin: float
 * }
 */
function chandra_yallop_width_prime_arcmin(
    float $moonDistKm,
    float $geocentricMoonAltDeg,
    float $arclGeoDeg,
): array {
    $sinPi = min(1.0, CHANDRA_YALLOP_WGS84_A_KM / max($moonDistKm, 1.0));
    $piRad = asin($sinPi);
    $piArcmin = rad2deg($piRad) * 60.0;
    $piDeg = rad2deg($piRad);

    // TN 69 (3.8): SD = 0.27245 × π  (same unit)
    $sd = CHANDRA_YALLOP_MOON_RADIUS_FACTOR * $piArcmin;
    // TN 69 (3.9): SD′ = SD (1 + sin h sin π)
    $sdPrime = $sd * (1.0 + sin(deg2rad($geocentricMoonAltDeg)) * $sinPi);
    // TN 69 (3.10): W′ = SD′ (1 − cos ARCL)
    $wPrime = $sdPrime * (1.0 - cos(deg2rad($arclGeoDeg)));

    return [
        'sd_geo_arcmin' => $sd,
        'sd_prime_arcmin' => $sdPrime,
        'w_prime_arcmin' => $wPrime,
        'parallax_deg' => $piDeg,
        'parallax_rad' => $piRad,
        'parallax_arcmin' => $piArcmin,
    ];
}

function chandra_yallop_poly_arcv_deg(float $wPrimeArcmin): float
{
    $w = $wPrimeArcmin;
    $w2 = $w * $w;
    $w3 = $w2 * $w;

    return CHANDRA_YALLOP_POLY_C0
        + CHANDRA_YALLOP_POLY_C1 * $w
        + CHANDRA_YALLOP_POLY_C2 * $w2
        + CHANDRA_YALLOP_POLY_C3 * $w3;
}

/**
 * Directed ecliptic separation Moon − Sun in [0, 360): waxing iff in (0, 180).
 *
 * @return array{directed_sep_deg: float, is_waxing: bool, sun_lon_deg: float, moon_lon_deg: float, ok: bool}
 */
function chandra_yallop_directed_waxing(JmeEphFFI $jme, float $jd): array
{
    $sun = chandra_yallop_ecliptic($jme, $jd, JmeEphFFI::JME_BODY_SUN);
    $moon = chandra_yallop_ecliptic($jme, $jd, JmeEphFFI::JME_BODY_MOON);
    if (!$sun['ok'] || !$moon['ok']) {
        return [
            'directed_sep_deg' => 0.0,
            'is_waxing' => false,
            'sun_lon_deg' => 0.0,
            'moon_lon_deg' => 0.0,
            'ok' => false,
        ];
    }

    $sep = chandra_yallop_norm_deg((float) $moon['lon_deg'] - (float) $sun['lon_deg']);

    return [
        'directed_sep_deg' => $sep,
        'is_waxing' => $sep > 0.0 && $sep < 180.0,
        'sun_lon_deg' => (float) $sun['lon_deg'],
        'moon_lon_deg' => (float) $moon['lon_deg'],
        'ok' => true,
    ];
}

/**
 * Evaluate one evening at Yallop best time.
 *
 * @return array<string, mixed>
 */
function chandra_yallop_evaluate_evening(
    JmeEphFFI $jme,
    float $sunsetJd,
    float $moonsetJd,
    float $lat,
    float $lon,
    float $elevationM = 0.0,
): array {
    $lagDays = $moonsetJd - $sunsetJd;
    $lagMinutes = $lagDays * 1440.0;
    if ($lagMinutes <= 0.0) {
        return [
            'ok' => false,
            'status' => 'NO_POSITIVE_LAG',
            'q' => null,
            'category' => null,
            'lag_minutes' => $lagMinutes,
            'lag_days' => $lagDays,
        ];
    }

    // TN 69 (4.1): Tb = Ts + (4/9) Lag
    $bestJd = $sunsetJd + (4.0 / 9.0) * $lagDays;

    $sunGeo = chandra_yallop_equatorial($jme, $bestJd, JmeEphFFI::JME_BODY_SUN);
    $moonGeo = chandra_yallop_equatorial($jme, $bestJd, JmeEphFFI::JME_BODY_MOON);
    if (!$sunGeo['ok'] || !$moonGeo['ok']) {
        return [
            'ok' => false,
            'status' => 'JME_EQUATORIAL_FAILED',
            'error' => $sunGeo['error'] !== '' ? $sunGeo['error'] : $moonGeo['error'],
            'q' => null,
            'category' => null,
            'lag_minutes' => $lagMinutes,
            'best_time_jd' => $bestJd,
        ];
    }

    $lst = chandra_yallop_lst_deg($jme, $bestJd, $lon);
    $moonDistKm = max(1.0, (float) $moonGeo['dist_au'] * CHANDRA_YALLOP_AU_KM);

    // --- Yallop ARCL / ARCV / DAZ: geocentric (TN 69) ---
    $arcl = chandra_yallop_angular_sep_deg(
        (float) $sunGeo['ra_deg'],
        (float) $sunGeo['dec_deg'],
        (float) $moonGeo['ra_deg'],
        (float) $moonGeo['dec_deg'],
    );

    $sunHz = chandra_yallop_alt_az(
        (float) $sunGeo['ra_deg'],
        (float) $sunGeo['dec_deg'],
        $lst,
        $lat,
    );
    $moonHz = chandra_yallop_alt_az(
        (float) $moonGeo['ra_deg'],
        (float) $moonGeo['dec_deg'],
        $lst,
        $lat,
    );

    $arcv = (float) $moonHz['alt_deg'] - (float) $sunHz['alt_deg'];
    $daz = abs((float) $moonHz['az_deg'] - (float) $sunHz['az_deg']);
    if ($daz > 180.0) {
        $daz = 360.0 - $daz;
    }

    // Audit only (does not affect q): exact spherical separation from horizontal
    // coords — cos c = sin h⊙ sin h☽ + cos h⊙ cos h☽ cos(DAZ)
    // (not the approximate cos ARCL ≟ cos ARCV · cos DAZ relation).
    $cosArclHorizontal =
        sin(deg2rad((float) $sunHz['alt_deg'])) * sin(deg2rad((float) $moonHz['alt_deg']))
        + cos(deg2rad((float) $sunHz['alt_deg'])) * cos(deg2rad((float) $moonHz['alt_deg']))
        * cos(deg2rad($daz));
    if ($cosArclHorizontal > 1.0) {
        $cosArclHorizontal = 1.0;
    } elseif ($cosArclHorizontal < -1.0) {
        $cosArclHorizontal = -1.0;
    }

    $arclFromHorizontal = rad2deg(acos($cosArclHorizontal));
    $arclConsistencyErrorDeg = abs($arcl - $arclFromHorizontal);

    // --- W′ only: topocentric width (TN 69 3.8–3.10), ARCL geocentric ---
    $width = chandra_yallop_width_prime_arcmin(
        $moonDistKm,
        (float) $moonHz['alt_deg'],
        $arcl,
    );
    $wPrime = (float) $width['w_prime_arcmin'];

    // Optional topo Moon (audit only — not used in q)
    $moonTop = chandra_yallop_topocentric_moon(
        (float) $moonGeo['ra_deg'],
        (float) $moonGeo['dec_deg'],
        (float) $moonGeo['dist_au'],
        $lst,
        $lat,
        $elevationM,
    );

    $pheno = chandra_yallop_pheno_moon($jme, $bestJd);
    $wax = chandra_yallop_directed_waxing($jme, $bestJd);
    $isWaxing = (bool) $wax['is_waxing'];

    // Always compute published q polynomial (no sentinel injection into q).
    // Category/label come only from q — never overwritten by Danjon application policy.
    $poly = chandra_yallop_poly_arcv_deg($wPrime);
    $q = ($arcv - $poly) / 10.0;
    [$categoryFromQ, $labelFromQ] = chandra_yallop_classify_q($q);

    // Diagnostic only: ARCL below 7° (resolver may reject if guard enabled).
    $belowDanjon = $arcl < CHANDRA_YALLOP_DANJON_ARCL_DEG;

    $base = [
        'ok' => true,
        'best_time_jd' => $bestJd,
        'sunset_jd' => $sunsetJd,
        'moonset_jd' => $moonsetJd,
        'lag_days' => $lagDays,
        'lag_minutes' => $lagMinutes,
        'lst_deg' => $lst,
        'lst_source' => 'jme_sidereal_time_GMST_x15_plus_east_longitude',
        'jme_sidereal_time_convention' => 'GMST',
        'equatorial_coordinate_frame' => 'mean_equator_equinox_of_date',
        'equatorial_nutation_applied' => false,
        'coordinate_pair' => 'mean_RA_Dec_plus_GMST',
        'coordinate_time_consistency_verified' => true,
        'observer' => [
            'latitude_deg' => $lat,
            'longitude_deg' => $lon,
            'elevation_m' => $elevationM,
            'observer_model_for_q' => 'geocentric_horizon_at_geodetic_latitude',
            'elevation_used_in_yallop_q' => false,
            'elevation_used_in_optional_topocentric_audit' => true,
            'wgs84_topocentric_moon_audit_only' => true,
        ],
        'sun_geocentric' => $sunGeo,
        'moon_geocentric' => $moonGeo,
        'moon_topocentric_audit_only' => $moonTop,
        // Geocentric horizon coords (Yallop ARCV/DAZ)
        'sun_alt_geocentric_deg' => $sunHz['alt_deg'],
        'sun_az_geocentric_deg' => $sunHz['az_deg'],
        'moon_alt_geocentric_deg' => $moonHz['alt_deg'],
        'moon_az_geocentric_deg' => $moonHz['az_deg'],
        // Aliases used by resolver display
        'sun_alt_deg' => $sunHz['alt_deg'],
        'moon_alt_deg' => $moonHz['alt_deg'],
        // Yallop primary inputs
        'arcl_deg' => $arcl,
        'arcl_coordinate_frame' => 'geocentric',
        'arcv_deg' => $arcv,
        'arcv_coordinate_frame' => 'geocentric_airless',
        'daz_deg' => $daz,
        'daz_coordinate_frame' => 'geocentric',
        'arcl_from_horizontal_altaz_deg' => $arclFromHorizontal,
        'arcl_consistency_error_deg' => $arclConsistencyErrorDeg,
        // Independent exact spherical audit — not TN 69 equation 2.1 (cos ARCL = cos ARCV cos DAZ).
        'arcl_consistency_method' => 'spherical_altaz_separation_vs_equatorial_arcl',
        'arcl_consistency_is_tn69_eq_2_1' => false,
        // Width
        'width_arcmin' => $wPrime,
        'w_prime_arcmin' => $wPrime,
        'sd_geocentric_arcmin' => $width['sd_geo_arcmin'],
        'sd_prime_arcmin' => $width['sd_prime_arcmin'],
        'parallax_horizontal_deg' => $width['parallax_deg'],
        'parallax_horizontal_arcmin' => $width['parallax_arcmin'],
        'width_method' => 'yallop_tn69_eq_3_8_to_3_10_sd_equals_k_times_parallax',
        'sd_formula' => 'SD = 0.27245 * arcsin(a/Δ)  [same unit as π]',
        // Waxing (directed ecliptic)
        'is_waxing' => $isWaxing,
        'directed_ecliptic_sep_deg' => $wax['directed_sep_deg'],
        'directed_waxing_ok' => $wax['ok'],
        'sun_ecliptic_lon_deg' => $wax['sun_lon_deg'],
        'moon_ecliptic_lon_deg' => $wax['moon_lon_deg'],
        'pheno' => $pheno,
        // q
        'q' => $q,
        'computed_q' => $q,
        'yallop_poly_arcv_threshold_deg' => $poly,
        'yallop_poly_coeffs' => [
            'c0' => CHANDRA_YALLOP_POLY_C0,
            'c1' => CHANDRA_YALLOP_POLY_C1,
            'c2' => CHANDRA_YALLOP_POLY_C2,
            'c3' => CHANDRA_YALLOP_POLY_C3,
            'W_unit' => 'arcmin_topocentric_W_prime',
        ],
        // q-category only from published polynomial bands (never Danjon-overridden).
        'q_category' => $categoryFromQ,
        'q_label' => $labelFromQ,
        'category_from_q' => $categoryFromQ,
        'label_from_q' => $labelFromQ,
        'category' => $categoryFromQ,
        'label' => $labelFromQ,
        // Danjon is diagnostic + resolver policy — not part of q polynomial.
        'danjon_guard_condition_met' => $belowDanjon,
        'danjon' => [
            'condition_met' => $belowDanjon,
            'threshold_deg' => CHANDRA_YALLOP_DANJON_ARCL_DEG,
            'enabled_by_resolver' => null,
            'rejected_this_evening' => null,
            'part_of_q_polynomial' => false,
            'is_application_policy' => true,
        ],
        // Legacy aliases (condition only; not "policy applied")
        'danjon_guard_applied' => $belowDanjon,
        'danjon_guard_degrees' => CHANDRA_YALLOP_DANJON_ARCL_DEG,
        'danjon_guard_threshold_deg' => CHANDRA_YALLOP_DANJON_ARCL_DEG,
        'danjon_guard_is_application_policy' => true,
        'danjon_guard_is_part_of_q_polynomial' => false,
        'model' => 'yallop_1997_q_tn69_geocentric_arcl_arcv_topocentric_w_prime',
        'reference' => 'Yallop_HMNAO_TN_69_1997',
        'claim' => 'Modern ephemeris implementation of Yallop 1997 TN69 q-criterion: geocentric ARCL/ARCV/DAZ, topocentric W′ via SD=0.27245·π, mean RA/Dec paired with GMST, and published best-time and category formulas. Not bit-identical HMNAO internal tables.',
        'precision_notes' => [
            'binary64_throughout' => true,
            'no_decision_rounding' => true,
            'arcl_geocentric' => true,
            'arcv_geocentric_airless' => true,
            'w_prime_topocentric_sd_only' => true,
            'sd_equals_k_times_horizontal_parallax_tn69_3_8' => true,
            'not_arcsin_of_k_a_over_delta' => true,
            'best_time_exact_4_over_9_lag' => true,
            'ephemeris_recomputed_at_best_time' => true,
            'yallop_poly_coefficients_published' => true,
            'q_never_replaced_by_sentinel' => true,
            'q_category_never_overwritten_by_danjon' => true,
            'danjon_is_application_policy_not_q_polynomial' => true,
            'elevation_not_in_yallop_q' => true,
            'not_claiming_nao_internal_bit_identical_tables' => true,
            'jme_sidereal_time_convention_verified_as_GMST' => true,
            'equatorial_no_nutation_for_mean_frame' => true,
            'coordinate_time_consistency_verified' => true,
            'arcl_consistency_uses_exact_horizontal_sphere' => true,
            'arcl_consistency_does_not_affect_q' => true,
        ],
    ];

    if (!$isWaxing) {
        return array_merge($base, [
            'status' => 'NOT_WAXING',
            // keep computed_q; calendar gate will reject
        ]);
    }

    // Policy-neutral: Danjon reject is resolver-only.
    return array_merge($base, [
        'status' => 'COMPUTED',
    ]);
}

/**
 * @return array{0: string, 1: string}
 */
function chandra_yallop_classify_q(float $q): array
{
    if ($q > 0.216) {
        return ['A', 'EASILY_VISIBLE'];
    }

    if ($q > -0.014) {
        return ['B', 'VISIBLE_PERFECT_CONDITIONS'];
    }

    if ($q > -0.160) {
        return ['C', 'OPTICAL_AID_FIRST_MAYBE_NAKED_EYE'];
    }

    if ($q > -0.232) {
        return ['D', 'OPTICAL_AID_ONLY'];
    }

    if ($q > -0.293) {
        return ['E', 'NOT_VISIBLE'];
    }

    return ['F', 'BELOW_DANJON_LIKE_Q'];
}

function chandra_yallop_min_q_for_category(string $minCategory): float
{
    return match (strtoupper($minCategory)) {
        'A' => 0.216,
        'B' => -0.014,
        'C' => -0.160,
        'D' => -0.232,
        default => -0.014,
    };
}

/**
 * Accept if raw q exceeds category floor.
 *
 * Optional third argument: when true, treat Danjon ARCL guard as active rejection
 * (application policy; does not alter computed_q).
 */
function chandra_yallop_passes(float $q, string $minCategory, bool $rejectForDanjonGuard = false): bool
{
    if ($rejectForDanjonGuard) {
        return false;
    }

    return $q > chandra_yallop_min_q_for_category($minCategory);
}

/**
 * Margin of q above the category floor (positive ⇒ pass by that amount).
 */
function chandra_yallop_q_boundary_margin(float $q, string $minCategory): float
{
    return $q - chandra_yallop_min_q_for_category($minCategory);
}

/**
 * Near-boundary flag for coordinate-time verification sensitivity.
 */
function chandra_yallop_coordinate_consistency_sensitivity(float $qBoundaryMargin, float $threshold = 0.001): string
{
    return abs($qBoundaryMargin) < $threshold
        ? 'NEAR_BOUNDARY_REQUIRES_VERIFICATION'
        : 'NOT_NEAR_BOUNDARY';
}
