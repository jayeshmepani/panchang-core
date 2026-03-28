<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Astronomy;

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Astronomy\Concerns\ConfiguresEphemeris;
use JayeshMepani\PanchangCore\Core\AstroCore;
use JayeshMepani\PanchangCore\Panchanga\ElectionalRuleBook;
use SwissEph\FFI\SwissEphFFI;

/**
 * Astronomy Service.
 *
 * Provides astronomical calculations using Swiss Ephemeris:
 * - Julian Day conversions
 * - Ayanamsa calculations
 * - Planet position calculations
 * - Sunrise/sunset calculations
 */
class AstronomyService
{
    use ConfiguresEphemeris;

    private static string $ayanamsa = 'LAHIRI';

    public function __construct(private SwissEphFFI $sweph)
    {
        $this->initializeEphemerisPath($this->sweph);
    }

    /**
     * Configure service (optional, for standalone usage).
     *
     * @param string $ephePath Ephemeris path (empty for default)
     * @param string $ayanamsaMode Ayanamsa mode ('LAHIRI', 'RAMAN', 'KRISHNAMURTI')
     */
    public static function configure(string $ephePath = '', string $ayanamsaMode = 'LAHIRI'): void
    {
        self::setEphemerisPath($ephePath);
        self::$ayanamsa = $ayanamsaMode;
    }

    /**
     * Convert birth array to Julian Day (UTC).
     *
     * @param array{year:int, month:int, day:int, hour:int, minute:int, second:int, timezone:string} $birth Birth data
     *
     * @return float Julian Day in UTC
     */
    public function toJulianDayUtc(array $birth): float
    {
        $local = CarbonImmutable::create(
            (int) $birth['year'],
            (int) $birth['month'],
            (int) $birth['day'],
            (int) $birth['hour'],
            (int) $birth['minute'],
            (int) $birth['second'],
            $birth['timezone']
        );

        $utc = $local->setTimezone('UTC');
        $hourDecimal = (int) $utc->format('H')
            + ((int) $utc->format('i')) / 60.0
            + ((int) $utc->format('s')) / 3600.0;

        return $this->sweph->swe_julday(
            $utc->year,
            $utc->month,
            $utc->day,
            $hourDecimal,
            SwissEphFFI::SE_GREG_CAL
        );
    }

    /**
     * Set ayanamsa mode.
     *
     * @param float $jd Julian Day
     */
    public function setAyanamsa(float $jd): void
    {
        $ayanamsaMode = self::$ayanamsa ?: (function_exists('config') ? config('panchang.ayanamsa', getenv('PANCHANG_AYANAMSA') ?: 'LAHIRI') : (getenv('PANCHANG_AYANAMSA') ?: 'LAHIRI'));

        $mode = match (strtoupper((string) $ayanamsaMode)) {
            'LAHIRI' => SwissEphFFI::SE_SIDM_LAHIRI,
            'RAMAN' => SwissEphFFI::SE_SIDM_RAMAN,
            'KRISHNAMURTI' => SwissEphFFI::SE_SIDM_KRISHNAMURTI,
            default => SwissEphFFI::SE_SIDM_LAHIRI,
        };

        $this->sweph->swe_set_sid_mode($mode, 0.0, 0.0);
    }

    public function getPlanets(array $birth): array
    {
        $jd = $this->toJulianDayUtc($birth);
        $this->setAyanamsa($jd);

        $flags = SwissEphFFI::SEFLG_SWIEPH | SwissEphFFI::SEFLG_SIDEREAL;
        $planets = [
            'Sun' => SwissEphFFI::SE_SUN,
            'Moon' => SwissEphFFI::SE_MOON,
            'Mars' => SwissEphFFI::SE_MARS,
            'Mercury' => SwissEphFFI::SE_MERCURY,
            'Jupiter' => SwissEphFFI::SE_JUPITER,
            'Venus' => SwissEphFFI::SE_VENUS,
            'Saturn' => SwissEphFFI::SE_SATURN,
            'Rahu' => SwissEphFFI::SE_MEAN_NODE,
        ];

        $out = [];
        $xx = $this->sweph->getFFI()->new('double[6]');
        $serr = $this->sweph->getFFI()->new('char[256]');

        foreach ($planets as $name => $pid) {
            $this->sweph->swe_calc_ut($jd, $pid, $flags, $xx, $serr);
            $out[$name] = AstroCore::normalize($xx[0]);
        }

        $out['Ketu'] = AstroCore::normalize($out['Rahu'] + 180.0);

        // Ensure all planet longitudes use configured precision
        foreach ($out as $name => $lon) {
            $out[$name] = $lon;
        }

        return $out;
    }

    public function getPlanetaryStates(array $birth, string $nodeMode = 'mean', float $stationTolerance = 1.0e-6): array
    {
        $jd = $this->toJulianDayUtc($birth);
        $this->setAyanamsa($jd);

        $flags = SwissEphFFI::SEFLG_SWIEPH | SwissEphFFI::SEFLG_SIDEREAL | SwissEphFFI::SEFLG_SPEED;
        $rahupId = strtoupper($nodeMode) === 'TRUE' ? SwissEphFFI::SE_TRUE_NODE : SwissEphFFI::SE_MEAN_NODE;
        $planets = [
            'Sun' => SwissEphFFI::SE_SUN,
            'Moon' => SwissEphFFI::SE_MOON,
            'Mars' => SwissEphFFI::SE_MARS,
            'Mercury' => SwissEphFFI::SE_MERCURY,
            'Jupiter' => SwissEphFFI::SE_JUPITER,
            'Venus' => SwissEphFFI::SE_VENUS,
            'Saturn' => SwissEphFFI::SE_SATURN,
            'Rahu' => $rahupId,
        ];

        $xx = $this->sweph->getFFI()->new('double[6]');
        $serr = $this->sweph->getFFI()->new('char[256]');

        $sunLongitude = null;
        $states = [];

        foreach ($planets as $name => $pid) {
            $this->sweph->swe_calc_ut($jd, $pid, $flags, $xx, $serr);
            $longitude = AstroCore::normalize($xx[0]);
            $speed = (float) $xx[3];

            if ($name === 'Sun') {
                $sunLongitude = $longitude;
            }

            $isRetrograde = match ($name) {
                'Moon' => false,
                'Rahu' => strtoupper($nodeMode) === 'MEAN' ? true : $speed < 0.0,
                default => $speed < 0.0,
            };

            $separation = $sunLongitude === null ? 0.0 : $this->minimalAngularSeparation($longitude, $sunLongitude);
            $orb = null;
            if ($name !== 'Sun') {
                $orb = $isRetrograde && isset(ElectionalRuleBook::COMBUSTION_ORBS_RETRO[$name])
                    ? ElectionalRuleBook::COMBUSTION_ORBS_RETRO[$name]
                    : (ElectionalRuleBook::COMBUSTION_ORBS[$name] ?? null);
            }

            $isCombust = false;
            if ($name !== 'Sun' && $name !== 'Rahu' && $name !== 'Ketu' && $orb !== null) {
                $outerRetrogradeExempt = in_array($name, ['Mars', 'Jupiter', 'Saturn'], true) && $isRetrograde;
                $isCombust = !$outerRetrogradeExempt && $separation <= $orb;
            }

            $states[$name] = [
                'lon' => $longitude,
                'speed_deg_per_day' => $speed,
                'is_retrograde' => $isRetrograde,
                'is_stationary' => abs($speed) <= $stationTolerance,
                'is_combust' => $isCombust,
                'separation_from_sun' => $separation,
                'orb_used' => $orb,
            ];
        }

        if (isset($states['Rahu'])) {
            $ketuLongitude = AstroCore::normalize((float) $states['Rahu']['lon'] + 180.0);
            $states['Ketu'] = [
                'lon' => $ketuLongitude,
                'speed_deg_per_day' => -((float) $states['Rahu']['speed_deg_per_day']),
                'is_retrograde' => (bool) $states['Rahu']['is_retrograde'],
                'is_stationary' => (bool) $states['Rahu']['is_stationary'],
                'is_combust' => false,
                'separation_from_sun' => $sunLongitude === null ? 0.0 : $this->minimalAngularSeparation($ketuLongitude, $sunLongitude),
                'orb_used' => null,
            ];
        }

        return $states;
    }

    public function getAscendant(array $birth): float
    {
        $jd = $this->toJulianDayUtc($birth);
        $this->setAyanamsa($jd);

        $ayanamsa = $this->sweph->swe_get_ayanamsa_ut($jd);

        $cusps = $this->sweph->getFFI()->new('double[13]');
        $ascmc = $this->sweph->getFFI()->new('double[10]');

        $this->sweph->swe_houses(
            $jd,
            (float) $birth['latitude'],
            (float) $birth['longitude'],
            ord('W'),
            $cusps,
            $ascmc
        );

        return AstroCore::normalize($ascmc[0] - $ayanamsa);
    }

    public function getAyanamsa(float $jd): float
    {
        $this->setAyanamsa($jd);
        return $this->sweph->swe_get_ayanamsa_ut($jd);
    }

    private function minimalAngularSeparation(float $a, float $b): float
    {
        $delta = abs(AstroCore::normalize($a) - AstroCore::normalize($b));
        return min($delta, 360.0 - $delta);
    }
}
