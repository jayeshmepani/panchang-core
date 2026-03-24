<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Astronomy;

use Carbon\CarbonImmutable;
use SwissEph\FFI\SwissEphFFI;

class SunService
{
    private static string $ephePath = '';

    public function __construct(private SwissEphFFI $sweph)
    {
        $ephePath = self::$ephePath ?: getenv('PANCHANG_EPHE_PATH') ?: '';
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
    }

    /** Get sunrise and sunset as timezone-aware Carbon instances. */
    public function getSunriseSunset(array $birth): array
    {
        $jd = $this->sweph->swe_julday(
            (int) $birth['year'],
            (int) $birth['month'],
            (int) $birth['day'],
            0.0,
            SwissEphFFI::SE_GREG_CAL
        );

        $geopos = $this->sweph->getFFI()->new('double[3]');
        $geopos[0] = (float) $birth['longitude'];
        $geopos[1] = (float) $birth['latitude'];
        $geopos[2] = (float) ($birth['elevation'] ?? 0.0);

        $trise = $this->sweph->getFFI()->new('double[1]');
        $tset = $this->sweph->getFFI()->new('double[1]');
        $serr = $this->sweph->getFFI()->new('char[256]');

        $this->sweph->swe_rise_trans(
            $jd,
            SwissEphFFI::SE_SUN,
            '',
            SwissEphFFI::SEFLG_SWIEPH,
            SwissEphFFI::SE_CALC_RISE,
            $geopos,
            1013.25,
            15.0,
            $trise,
            $serr
        );

        $this->sweph->swe_rise_trans(
            $jd,
            SwissEphFFI::SE_SUN,
            '',
            SwissEphFFI::SEFLG_SWIEPH,
            SwissEphFFI::SE_CALC_SET,
            $geopos,
            1013.25,
            15.0,
            $tset,
            $serr
        );

        $tz = $birth['timezone'];

        $sunrise = $this->jdToCarbon($trise[0], $tz);
        $sunset = $this->jdToCarbon($tset[0], $tz);

        return [$sunrise, $sunset];
    }

    public function getMoonriseMoonset(array $birth): array
    {
        $jd = $this->sweph->swe_julday(
            $birth['year'], $birth['month'], $birth['day'],
            0.0,
            SwissEphFFI::SE_GREG_CAL
        );

        $geopos = $this->newGeoPos($birth);
        $tret = $this->sweph->getFFI()->new('double[1]');
        $serr = $this->sweph->getFFI()->new('char[256]');

        $this->sweph->swe_rise_trans(
            $jd,
            SwissEphFFI::SE_MOON,
            '',
            SwissEphFFI::SEFLG_SWIEPH,
            SwissEphFFI::SE_CALC_RISE,
            $geopos,
            1013.25,
            15.0,
            $tret,
            $serr
        );
        $moonrise = $this->jdToCarbon($tret[0], $birth['timezone']);

        $this->sweph->swe_rise_trans(
            $jd,
            SwissEphFFI::SE_MOON,
            '',
            SwissEphFFI::SEFLG_SWIEPH,
            SwissEphFFI::SE_CALC_SET,
            $geopos,
            1013.25,
            15.0,
            $tret,
            $serr
        );
        $moonset = $this->jdToCarbon($tret[0], $birth['timezone']);

        return [$moonrise, $moonset];
    }

    public function jdToCarbonPublic(float $jd, string $timezone): CarbonImmutable
    {
        return $this->jdToCarbon($jd, $timezone);
    }

    public function getTwilightTimes(array $birth): array
    {
        return [
            'civil' => $this->getRiseSetWithFlag($birth, SwissEphFFI::SE_BIT_CIVIL_TWILIGHT),
            'nautical' => $this->getRiseSetWithFlag($birth, SwissEphFFI::SE_BIT_NAUTIC_TWILIGHT),
            'astronomical' => $this->getRiseSetWithFlag($birth, SwissEphFFI::SE_BIT_ASTRO_TWILIGHT),
        ];
    }

    public function getSolarTransits(array $birth): array
    {
        $jd = $this->sweph->swe_julday(
            $birth['year'], $birth['month'], $birth['day'],
            0.0,
            SwissEphFFI::SE_GREG_CAL
        );
        $geopos = $this->newGeoPos($birth);

        $tret = $this->sweph->getFFI()->new('double[1]');
        $serr = $this->sweph->getFFI()->new('char[256]');

        $this->sweph->swe_rise_trans(
            $jd,
            SwissEphFFI::SE_SUN,
            '',
            SwissEphFFI::SEFLG_SWIEPH,
            SwissEphFFI::SE_CALC_MTRANSIT,
            $geopos,
            1013.25,
            15.0,
            $tret,
            $serr
        );
        $solarNoon = $this->jdToCarbon($tret[0], $birth['timezone']);

        $this->sweph->swe_rise_trans(
            $jd,
            SwissEphFFI::SE_SUN,
            '',
            SwissEphFFI::SEFLG_SWIEPH,
            SwissEphFFI::SE_CALC_ITRANSIT,
            $geopos,
            1013.25,
            15.0,
            $tret,
            $serr
        );
        $solarMidnight = $this->jdToCarbon($tret[0], $birth['timezone']);

        return [
            'solar_noon' => $solarNoon,
            'solar_midnight' => $solarMidnight,
        ];
    }

    /** Get birth moment as timezone-aware Carbon. */
    public function getBirthDatetime(array $birth): CarbonImmutable
    {
        return CarbonImmutable::create(
            (int) $birth['year'],
            (int) $birth['month'],
            (int) $birth['day'],
            (int) $birth['hour'],
            (int) $birth['minute'],
            (int) $birth['second'],
            $birth['timezone']
        );
    }

    /** Determine if birth occurred during daytime. */
    public function isDayBirth(array $birth): bool
    {
        [$sunrise, $sunset] = $this->getSunriseSunset($birth);
        $birthDt = $this->getBirthDatetime($birth);

        return $birthDt->greaterThanOrEqualTo($sunrise)
            && $birthDt->lessThan($sunset);
    }

    /** Get interval duration (seconds), start time, and day/night flag. */
    public function getIntervalAndStart(array $birth): array
    {
        [$sunrise, $sunset] = $this->getSunriseSunset($birth);
        $birthDt = $this->getBirthDatetime($birth);
        $isDay = $birthDt->greaterThanOrEqualTo($sunrise)
            && $birthDt->lessThan($sunset);

        if ($isDay) {
            $interval = $sunset->diffInSeconds($sunrise);
            $start = $sunrise;
        } else {
            $nextDate = CarbonImmutable::create(
                (int) $birth['year'],
                (int) $birth['month'],
                (int) $birth['day'],
                0,
                0,
                0,
                $birth['timezone']
            )->addDay();

            $nextBirth = $birth;
            $nextBirth['year'] = $nextDate->year;
            $nextBirth['month'] = $nextDate->month;
            $nextBirth['day'] = $nextDate->day;

            [$nextSunrise] = $this->getSunriseSunset($nextBirth);

            $interval = $nextSunrise->diffInSeconds($sunset);
            $start = $sunset;
        }

        return [$interval, $start, $isDay];
    }

    private function getRiseSetWithFlag(array $birth, int $twilightFlag): array
    {
        $jd = $this->sweph->swe_julday(
            $birth['year'], $birth['month'], $birth['day'],
            0.0,
            SwissEphFFI::SE_GREG_CAL
        );
        $geopos = $this->newGeoPos($birth);

        $tret = $this->sweph->getFFI()->new('double[1]');
        $serr = $this->sweph->getFFI()->new('char[256]');

        $this->sweph->swe_rise_trans(
            $jd,
            SwissEphFFI::SE_SUN,
            '',
            SwissEphFFI::SEFLG_SWIEPH,
            SwissEphFFI::SE_CALC_RISE | $twilightFlag,
            $geopos,
            1013.25,
            15.0,
            $tret,
            $serr
        );
        $rise = $this->jdToCarbon($tret[0], $birth['timezone']);

        $this->sweph->swe_rise_trans(
            $jd,
            SwissEphFFI::SE_SUN,
            '',
            SwissEphFFI::SEFLG_SWIEPH,
            SwissEphFFI::SE_CALC_SET | $twilightFlag,
            $geopos,
            1013.25,
            15.0,
            $tret,
            $serr
        );
        $set = $this->jdToCarbon($tret[0], $birth['timezone']);

        return ['dawn' => $rise, 'dusk' => $set];
    }

    private function newGeoPos(array $birth)
    {
        $geopos = $this->sweph->getFFI()->new('double[3]');
        $geopos[0] = (float) $birth['longitude'];
        $geopos[1] = (float) $birth['latitude'];
        $geopos[2] = (float) ($birth['elevation'] ?? 0.0);
        return $geopos;
    }

    /** Convert a Julian Day number (UT) to a timezone-aware Carbon instance. */
    private function jdToCarbon(float $jd, string $timezone): CarbonImmutable
    {
        $y = $this->sweph->getFFI()->new('int[1]');
        $mo = $this->sweph->getFFI()->new('int[1]');
        $d = $this->sweph->getFFI()->new('int[1]');
        $h = $this->sweph->getFFI()->new('int[1]');
        $mi = $this->sweph->getFFI()->new('int[1]');
        $s = $this->sweph->getFFI()->new('double[1]');

        $this->sweph->swe_jdut1_to_utc(
            $jd,
            SwissEphFFI::SE_GREG_CAL,
            $y,
            $mo,
            $d,
            $h,
            $mi,
            $s
        );

        $sInt = (int) $s[0];
        $micro = (int) floor(($s[0] - $sInt) * 1_000_000);

        return CarbonImmutable::create(
            (int) $y[0],
            (int) $mo[0],
            (int) $d[0],
            (int) $h[0],
            (int) $mi[0],
            $sInt,
            'UTC'
        )
            ->addMicroseconds($micro)
            ->setTimezone($timezone);
    }
}
