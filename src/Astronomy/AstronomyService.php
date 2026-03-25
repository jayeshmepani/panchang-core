<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Astronomy;

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Core\AstroCore;
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
    private static string $ephePath = '';
    private static string $ayanamsa = 'LAHIRI';

    public function __construct(private SwissEphFFI $sweph)
    {
        $ephePath = self::$ephePath ?: (function_exists('config') ? config('panchang.ephe_path', getenv('PANCHANG_EPHE_PATH') ?: '') : (getenv('PANCHANG_EPHE_PATH') ?: ''));
        if (is_string($ephePath) && $ephePath !== '' && file_exists($ephePath)) {
            $this->sweph->swe_set_ephe_path($ephePath);
        }
    }

    /**
     * Configure service (optional, for standalone usage).
     *
     * @param string $ephePath Ephemeris path (empty for default)
     * @param string $ayanamsaMode Ayanamsa mode ('LAHIRI', 'RAMAN', 'KRISHNAMURTI')
     */
    public static function configure(string $ephePath = '', string $ayanamsaMode = 'LAHIRI'): void
    {
        self::$ephePath = $ephePath;
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
        return $out;
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
}
